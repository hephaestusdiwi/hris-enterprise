<?php

namespace App\Modules\CashAdvance\Services;

use App\Models\User;
use App\Modules\CashAdvance\Enums\CashAdvanceRequestStatus;
use App\Modules\CashAdvance\Exceptions\CashAdvanceValidationException;
use App\Modules\CashAdvance\Models\CashAdvanceAttachment;
use App\Modules\CashAdvance\Models\CashAdvanceCategory;
use App\Modules\CashAdvance\Models\CashAdvancePolicy;
use App\Modules\CashAdvance\Models\CashAdvanceRequest;
use App\Modules\CashAdvance\Models\CashAdvanceRequestItem;
use App\Modules\CashAdvance\Support\CashAdvanceMath;
use App\Modules\Employee\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CashAdvanceService
{
    public function __construct(private CashAdvanceApprovalService $approvalService)
    {
    }

    /**
     * @param array{cash_advance_policy_id: int, purpose: string, date_of_use: string, notes?: ?string,
     *              items: array<int, array{cash_advance_category_id: int, name: string, description?: ?string, amount: string|float}>,
     *              attachments?: array<int, UploadedFile>} $data
     */
    public function submit(Employee $employee, array $data): CashAdvanceRequest
    {
        $policy = CashAdvancePolicy::find($data['cash_advance_policy_id']);

        if (! $policy || ! $policy->isCurrentlyEffective()) {
            throw new CashAdvanceValidationException('Policy tidak valid, tidak aktif, atau belum efektif.');
        }

        if (empty($data['items'])) {
            throw new CashAdvanceValidationException('Minimal satu item harus diisi.');
        }

        $allowedCategoryIds = $policy->categories()->pluck('cash_advance_categories.id')->all();

        $totalAmount = '0.00';
        $resolvedItems = [];

        foreach ($data['items'] as $item) {
            $category = CashAdvanceCategory::find($item['cash_advance_category_id']);

            if (! $category || ! $category->is_active) {
                throw new CashAdvanceValidationException('Ada kategori item yang tidak valid atau tidak aktif.');
            }

            if (! in_array($category->id, $allowedCategoryIds, true)) {
                throw new CashAdvanceValidationException("Kategori '{$category->name}' tidak tersedia untuk policy ini.");
            }

            $amount = (string) $item['amount'];

            if (! CashAdvanceMath::gte($amount, '0.01')) {
                throw new CashAdvanceValidationException('Amount setiap item harus lebih dari 0.');
            }

            $totalAmount = CashAdvanceMath::add($totalAmount, $amount);

            $resolvedItems[] = [
                'category_id' => $category->id,
                'name' => $item['name'],
                'description' => $item['description'] ?? null,
                'amount' => $amount,
            ];
        }

        return DB::transaction(function () use ($employee, $policy, $data, $resolvedItems, $totalAmount) {
            $request = CashAdvanceRequest::create([
                'employee_id' => $employee->id,
                'cash_advance_policy_id' => $policy->id,
                'purpose' => $data['purpose'],
                'date_of_use' => $data['date_of_use'],
                'notes' => $data['notes'] ?? null,
                'total_amount' => $totalAmount,
                'status' => CashAdvanceRequestStatus::PendingApproval->value,
                'submitted_at' => now(),
            ]);

            foreach ($resolvedItems as $item) {
                CashAdvanceRequestItem::create([
                    'cash_advance_request_id' => $request->id,
                    'cash_advance_category_id' => $item['category_id'],
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'amount' => $item['amount'],
                ]);
            }

            foreach ($data['attachments'] ?? [] as $file) {
                if ($file instanceof UploadedFile) {
                    $this->storeAttachment($request, $file);
                }
            }

            // Auto-approve kalau employee tidak punya approval flow -- sama
            // persis behavior Loan/Reimbursement, makanya dalam 1 transaction.
            $this->approvalService->initiate($request);

            return $request->fresh(['items.category', 'attachments']);
        });
    }

    public function cancel(CashAdvanceRequest $request, string $reason): CashAdvanceRequest
    {
        if (! in_array($request->status, [CashAdvanceRequestStatus::PendingApproval, CashAdvanceRequestStatus::Approved], true)) {
            throw new CashAdvanceValidationException('Request berstatus ini tidak bisa dibatalkan.');
        }

        if ($request->disbursed_at) {
            throw new CashAdvanceValidationException('Request yang sudah disbursed tidak bisa dibatalkan.');
        }

        return DB::transaction(function () use ($request, $reason) {
            $this->approvalService->cancelApprovalIfAny($request);

            $request->update([
                'status' => CashAdvanceRequestStatus::Cancelled->value,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            return $request->fresh();
        });
    }

    public function disburse(CashAdvanceRequest $request, ?string $note, ?User $actor): CashAdvanceRequest
    {
        if ($request->status !== CashAdvanceRequestStatus::Approved) {
            throw new CashAdvanceValidationException('Hanya request berstatus Approved yang bisa di-disburse.');
        }

        if ($request->disbursed_at) {
            throw new CashAdvanceValidationException('Request ini sudah pernah di-disburse sebelumnya.');
        }

        $request->update([
            'disbursed_at' => now(),
            'disbursed_by_user_id' => $actor?->id,
            'disbursement_note' => $note,
            'status' => CashAdvanceRequestStatus::NeedSettlement->value,
        ]);

        return $request->fresh();
    }

    private function storeAttachment(CashAdvanceRequest $request, UploadedFile $file): void
    {
        $path = $file->store("cash-advance-attachments/{$request->employee_id}", 'public');

        CashAdvanceAttachment::create([
            'cash_advance_request_id' => $request->id,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
        ]);
    }
}