<?php

namespace App\Modules\Reimbursement\Services;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Reimbursement\Enums\ReimbursementBalanceTransactionType;
use App\Modules\Reimbursement\Enums\ReimbursementRequestStatus;
use App\Modules\Reimbursement\Exceptions\ReimbursementValidationException;
use App\Modules\Reimbursement\Models\ReimbursementAttachment;
use App\Modules\Reimbursement\Models\ReimbursementBalance;
use App\Modules\Reimbursement\Models\ReimbursementBalanceTransaction;
use App\Modules\Reimbursement\Models\ReimbursementBenefit;
use App\Modules\Reimbursement\Models\ReimbursementRequest;
use App\Modules\Reimbursement\Models\ReimbursementRequestItem;
use App\Modules\Reimbursement\Support\ReimbursementMath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ReimbursementService
{
    public function __construct(
        private ReimbursementApprovalService $approvalService
    ) {
    }

    /**
     * @param array{
     *     reimbursement_balance_id: int,
     *     transaction_date: string,
     *     notes?: ?string,
     *     items: array<int, array{
     *         reimbursement_benefit_id: int,
     *         amount: string|float,
     *         notes?: ?string
     *     }>,
     *     attachments?: array<int, UploadedFile>
     * } $data
     */
    public function submit(
        Employee $employee,
        array $data
    ): ReimbursementRequest {
        $balance = ReimbursementBalance::findOrFail(
            $data['reimbursement_balance_id']
        );

        if (
            (int) $balance->employee_id !==
            $employee->id
        ) {
            throw new ReimbursementValidationException(
                'Balance ini bukan milik employee ini.'
            );
        }

        if (! $balance->isUsable()) {
            throw new ReimbursementValidationException(
                'Balance ini tidak aktif, belum efektif, atau sudah kadaluarsa.'
            );
        }

        $policy = $balance->policy;

        $totalAmount = '0.00';
        $resolvedItems = [];

        foreach ($data['items'] as $item) {
            $benefit = ReimbursementBenefit::find(
                $item['reimbursement_benefit_id']
            );

            if (
                ! $benefit ||
                (int) $benefit->reimbursement_policy_id !==
                    (int) $policy->id
            ) {
                throw new ReimbursementValidationException(
                    'Ada benefit/item yang tidak valid untuk policy balance ini.'
                );
            }

            if (! $benefit->is_active) {
                throw new ReimbursementValidationException(
                    "Benefit '{$benefit->name}' sudah tidak aktif."
                );
            }

            $amount = (string) $item['amount'];

            $totalAmount = ReimbursementMath::add(
                $totalAmount,
                $amount
            );

            $resolvedItems[] = [
                'benefit_id' => $benefit->id,
                'amount' => $amount,
                'notes' => $item['notes'] ?? null,
            ];
        }

        if (
            ! $balance->hasSufficientBalance(
                $totalAmount
            )
        ) {
            throw new ReimbursementValidationException(
                'Total amount melebihi sisa balance yang tersedia.'
            );
        }

        return DB::transaction(
            function () use (
                $employee,
                $policy,
                $balance,
                $data,
                $resolvedItems,
                $totalAmount
            ) {
                $request = ReimbursementRequest::create([
                    'employee_id' => $employee->id,
                    'reimbursement_policy_id' => $policy->id,
                    'reimbursement_balance_id' => $balance->id,
                    'transaction_date' =>
                        $data['transaction_date'],
                    'total_amount' => $totalAmount,
                    'notes' => $data['notes'] ?? null,
                    'status' =>
                        ReimbursementRequestStatus::Pending->value,
                ]);

                foreach ($resolvedItems as $item) {
                    ReimbursementRequestItem::create([
                        'reimbursement_request_id' =>
                            $request->id,
                        'reimbursement_benefit_id' =>
                            $item['benefit_id'],
                        'amount' => $item['amount'],
                        'notes' => $item['notes'],
                    ]);
                }

                foreach (
                    $data['attachments'] ?? []
                    as $file
                ) {
                    if ($file instanceof UploadedFile) {
                        $this->storeAttachment(
                            $request,
                            $file
                        );
                    }
                }

                $this->approvalService->initiate(
                    $request
                );

                return $request->fresh([
                    'items.benefit',
                    'attachments',
                ]);
            }
        );
    }

    public function cancel(
        ReimbursementRequest $request,
        string $reason
    ): ReimbursementRequest {
        if (
            ! in_array(
                $request->status,
                [
                    ReimbursementRequestStatus::Pending,
                    ReimbursementRequestStatus::Approved,
                ],
                true
            )
        ) {
            throw new ReimbursementValidationException(
                'Request berstatus ini tidak bisa dibatalkan.'
            );
        }

        if ($request->disbursed_at) {
            throw new ReimbursementValidationException(
                'Request yang sudah disbursed tidak bisa dibatalkan lagi.'
            );
        }

        return DB::transaction(
            function () use ($request, $reason) {
                $wasApproved =
                    $request->status ===
                    ReimbursementRequestStatus::Approved;

                $this->approvalService
                    ->cancelApprovalIfAny($request);

                $request->update([
                    'status' =>
                        ReimbursementRequestStatus::Cancelled->value,
                    'decided_at' => now(),
                    'cancel_reason' => $reason,
                ]);

                if ($wasApproved) {
                    $this->reverseClaim($request);
                }

                return $request->fresh();
            }
        );
    }

    /**
     * Disbursement hanya mencatat informasi pembayaran.
     * Balance sudah dipotong saat request Approved.
     */
    public function disburse(
        ReimbursementRequest $request,
        ?string $note,
        ?User $actor
    ): ReimbursementRequest {
        if (
            $request->status !==
                ReimbursementRequestStatus::Approved
        ) {
            throw new ReimbursementValidationException(
                'Hanya request berstatus Approved yang bisa diproses disbursement.'
            );
        }

        if ($request->disbursed_at) {
            throw new ReimbursementValidationException(
                'Request ini sudah pernah di-disburse sebelumnya.'
            );
        }

        $request->update([
            'disbursed_at' => now(),
            'disbursed_by_user_id' => $actor?->id,
            'disbursement_note' => $note,
        ]);

        return $request->fresh();
    }

    private function reverseClaim(
        ReimbursementRequest $request
    ): void {
        $balance = ReimbursementBalance::whereKey(
            $request->reimbursement_balance_id
        )
            ->lockForUpdate()
            ->first();

        if (
            ! $balance ||
            $balance->assigned_amount === null
        ) {
            return;
        }

        $lastTransaction = $balance
            ->transactions()
            ->latest('id')
            ->lockForUpdate()
            ->first();

        $currentRunning = $lastTransaction
            ? (string) $lastTransaction->running_balance
            : (string) $balance->assigned_amount;

        $newRunning = ReimbursementMath::add(
            $currentRunning,
            (string) $request->total_amount
        );

        ReimbursementBalanceTransaction::create([
            'reimbursement_balance_id' => $balance->id,
            'type' =>
                ReimbursementBalanceTransactionType::CancelReversal->value,
            'amount' => (string) $request->total_amount,
            'running_balance' => $newRunning,
            'reimbursement_request_id' => $request->id,
            'note' =>
                'Reversal pembatalan request #' .
                $request->id .
                '.',
        ]);
    }

    private function storeAttachment(
        ReimbursementRequest $request,
        UploadedFile $file
    ): void {
        $path = $file->store(
            "reimbursement-attachments/{$request->employee_id}",
            'public'
        );

        ReimbursementAttachment::create([
            'reimbursement_request_id' => $request->id,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
        ]);
    }
}