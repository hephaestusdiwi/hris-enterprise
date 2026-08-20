<?php

namespace App\Modules\Reimbursement\Services;

use App\Models\User;
use App\Modules\ApprovalFlow\Services\ApprovalFlowResolver;
use App\Modules\Attendance\Services\ApprovalStepApproverResolver;
use App\Modules\Reimbursement\Enums\ReimbursementApprovalRequestStatus;
use App\Modules\Reimbursement\Enums\ReimbursementApprovalStepDecisionStatus;
use App\Modules\Reimbursement\Enums\ReimbursementBalanceTransactionType;
use App\Modules\Reimbursement\Enums\ReimbursementRequestStatus;
use App\Modules\Reimbursement\Exceptions\ReimbursementApprovalException;
use App\Modules\Reimbursement\Models\ReimbursementApprovalRequest;
use App\Modules\Reimbursement\Models\ReimbursementApprovalStepDecision;
use App\Modules\Reimbursement\Models\ReimbursementBalance;
use App\Modules\Reimbursement\Models\ReimbursementBalanceTransaction;
use App\Modules\Reimbursement\Models\ReimbursementRequest;
use App\Modules\Reimbursement\Support\ReimbursementMath;
use Illuminate\Support\Facades\DB;

class ReimbursementApprovalService
{
    public function __construct(
        private ApprovalStepApproverResolver $resolver,
        private ApprovalFlowResolver $approvalFlowResolver,
    ) {
    }

    public function initiate(ReimbursementRequest $request): void
    {
        $employee = $request->employee;

        $approvalFlow = $this->approvalFlowResolver->resolveFor(
            $employee,
            'reimbursement'
        );

        if (! $approvalFlow) {
            $this->autoApprove($request);

            return;
        }

        $steps = $approvalFlow
            ->steps()
            ->where('is_active', true)
            ->orderBy('sequence')
            ->get();

        if ($steps->isEmpty()) {
            $this->autoApprove($request);

            return;
        }

        $approvalRequest = ReimbursementApprovalRequest::create([
            'reimbursement_request_id' => $request->id,
            'employee_id' => $employee->id,
            'approval_flow_id' => $approvalFlow->id,
            'status' => ReimbursementApprovalRequestStatus::Pending->value,
            'current_step_sequence' => $steps->first()->sequence,
            'requested_at' => now(),
        ]);

        foreach ($steps as $step) {
            ReimbursementApprovalStepDecision::create([
                'reimbursement_approval_request_id' => $approvalRequest->id,
                'approval_step_id' => $step->id,
                'sequence' => $step->sequence,
                'status' => ReimbursementApprovalStepDecisionStatus::Pending->value,
            ]);
        }
    }

    public function autoApprove(ReimbursementRequest $request): void
    {
        $this->applyApproval($request);
    }

    public function cancelApprovalIfAny(
        ReimbursementRequest $request
    ): void {
        $approvalRequest = $request->approvalRequest;

        if (
            $approvalRequest &&
            $approvalRequest->status ===
                ReimbursementApprovalRequestStatus::Pending
        ) {
            $approvalRequest->update([
                'decided_at' => now(),
            ]);
        }
    }

    public function decide(
        ReimbursementApprovalStepDecision $decision,
        User $actor,
        string $action,
        ?string $notes,
    ): ReimbursementApprovalRequest {
        $approvalRequest = $decision->request;

        if (
            $approvalRequest->request->status !==
                ReimbursementRequestStatus::Pending
        ) {
            throw new ReimbursementApprovalException(
                'Request ini sudah tidak pending (mungkin sudah dibatalkan).'
            );
        }

        if (
            $approvalRequest->status !==
                ReimbursementApprovalRequestStatus::Pending
        ) {
            throw new ReimbursementApprovalException(
                'Approval request ini sudah tidak pending.'
            );
        }

        if (
            $decision->sequence !==
                $approvalRequest->current_step_sequence
        ) {
            throw new ReimbursementApprovalException(
                'Bukan giliran step ini untuk diputuskan.'
            );
        }

        if (
            $decision->status !==
                ReimbursementApprovalStepDecisionStatus::Pending
        ) {
            throw new ReimbursementApprovalException(
                'Step ini sudah diputuskan sebelumnya.'
            );
        }

        $eligibleUserIds =
            $this->resolver->resolveApproverUserIds(
                $decision->approvalStep,
                $approvalRequest->employee
            );

        if (! in_array($actor->id, $eligibleUserIds, true)) {
            throw new ReimbursementApprovalException(
                'Anda tidak berwenang memutuskan approval ini.'
            );
        }

        return DB::transaction(
            function () use (
                $decision,
                $actor,
                $action,
                $notes,
                $approvalRequest
            ) {
                if ($action === 'reject') {
                    $decision->update([
                        'status' =>
                            ReimbursementApprovalStepDecisionStatus::Rejected->value,
                        'decided_by_user_id' => $actor->id,
                        'notes' => $notes,
                        'decided_at' => now(),
                    ]);

                    $approvalRequest->update([
                        'status' =>
                            ReimbursementApprovalRequestStatus::Rejected->value,
                        'decided_at' => now(),
                    ]);

                    $approvalRequest->request->update([
                        'status' =>
                            ReimbursementRequestStatus::Rejected->value,
                        'decided_at' => now(),
                    ]);

                    return $approvalRequest->fresh();
                }

                $decision->update([
                    'status' =>
                        ReimbursementApprovalStepDecisionStatus::Approved->value,
                    'decided_by_user_id' => $actor->id,
                    'notes' => $notes,
                    'decided_at' => now(),
                ]);

                $nextStep =
                    ReimbursementApprovalStepDecision::where(
                        'reimbursement_approval_request_id',
                        $approvalRequest->id
                    )
                        ->where(
                            'sequence',
                            '>',
                            $decision->sequence
                        )
                        ->orderBy('sequence')
                        ->first();

                if (! $nextStep) {
                    $approvalRequest->update([
                        'status' =>
                            ReimbursementApprovalRequestStatus::Approved->value,
                        'decided_at' => now(),
                    ]);

                    $this->applyApproval(
                        $approvalRequest->request
                    );
                } else {
                    $approvalRequest->update([
                        'current_step_sequence' =>
                            $nextStep->sequence,
                    ]);
                }

                return $approvalRequest->fresh();
            }
        );
    }

    /**
     * @return array<int, ReimbursementApprovalStepDecision>
     */
    public function pendingDecisionsForUser(User $user): array
    {
        $decisions = ReimbursementApprovalStepDecision::query()
            ->where(
                'status',
                ReimbursementApprovalStepDecisionStatus::Pending->value
            )
            ->whereHas(
                'request',
                fn ($query) => $query->where(
                    'status',
                    ReimbursementApprovalRequestStatus::Pending->value
                )
            )
            ->with([
                'approvalStep',
                'request.employee',
                'request.request.items.benefit',
            ])
            ->get()
            ->filter(
                fn (
                    ReimbursementApprovalStepDecision $decision
                ) =>
                    $decision->sequence ===
                    $decision->request->current_step_sequence
            )
            ->filter(
                function (
                    ReimbursementApprovalStepDecision $decision
                ) use ($user) {
                    $eligibleUserIds =
                        $this->resolver->resolveApproverUserIds(
                            $decision->approvalStep,
                            $decision->request->employee
                        );

                    return in_array(
                        $user->id,
                        $eligibleUserIds,
                        true
                    );
                }
            );

        return $decisions->values()->all();
    }

    private function applyApproval(
        ReimbursementRequest $request
    ): void {
        $balance = ReimbursementBalance::whereKey(
            $request->reimbursement_balance_id
        )
            ->lockForUpdate()
            ->first();

        if (
            $balance &&
            $balance->assigned_amount !== null
        ) {
            $lastTransaction = $balance
                ->transactions()
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $currentRunning = $lastTransaction
                ? (string) $lastTransaction->running_balance
                : (string) $balance->assigned_amount;

            if (
                ! ReimbursementMath::gte(
                    $currentRunning,
                    (string) $request->total_amount
                )
            ) {
                throw new ReimbursementApprovalException(
                    'Balance tidak cukup untuk approve request ini (kemungkinan sudah terpakai request lain).'
                );
            }

            $newRunning = ReimbursementMath::sub(
                $currentRunning,
                (string) $request->total_amount
            );

            ReimbursementBalanceTransaction::create([
                'reimbursement_balance_id' => $balance->id,
                'type' =>
                    ReimbursementBalanceTransactionType::Claim->value,
                'amount' => '-' . $request->total_amount,
                'running_balance' => $newRunning,
                'reimbursement_request_id' => $request->id,
                'note' =>
                    'Klaim disetujui untuk request #' .
                    $request->id .
                    '.',
            ]);
        }

        $request->update([
            'status' => ReimbursementRequestStatus::Approved->value,
            'decided_at' => now(),
        ]);
    }
}