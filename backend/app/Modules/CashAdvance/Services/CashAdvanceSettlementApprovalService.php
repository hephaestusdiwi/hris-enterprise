<?php

namespace App\Modules\CashAdvance\Services;

use App\Models\User;
use App\Modules\ApprovalFlow\Services\ApprovalFlowResolver;
use App\Modules\Attendance\Services\ApprovalStepApproverResolver;
use App\Modules\CashAdvance\Enums\CashAdvanceRequestStatus;
use App\Modules\CashAdvance\Enums\CashAdvanceSettlementApprovalRequestStatus;
use App\Modules\CashAdvance\Enums\CashAdvanceSettlementApprovalStepDecisionStatus;
use App\Modules\CashAdvance\Enums\CashAdvanceSettlementStatus;
use App\Modules\CashAdvance\Exceptions\CashAdvanceApprovalException;
use App\Modules\CashAdvance\Models\CashAdvanceSettlement;
use App\Modules\CashAdvance\Models\CashAdvanceSettlementApprovalRequest;
use App\Modules\CashAdvance\Models\CashAdvanceSettlementApprovalStepDecision;
use Illuminate\Support\Facades\DB;

/**
 * Pola sama persis dengan CashAdvanceApprovalService/LoanApprovalService,
 * tapi subject-nya CashAdvanceSettlement (bukan CashAdvanceRequest) --
 * sesuai spec: settlement approval terpisah dari request approval, dua-duanya
 * reuse ApprovalFlowResolver + ApprovalStepApproverResolver yang sama.
 */
class CashAdvanceSettlementApprovalService
{
    public function __construct(
        private ApprovalStepApproverResolver $resolver,
        private ApprovalFlowResolver $approvalFlowResolver,
    ) {
    }

    public function initiate(CashAdvanceSettlement $settlement): void
    {
        $employee = $settlement->request->employee;

        $approvalFlow = $this->approvalFlowResolver->resolveFor(
            $employee,
            'cash_advance_settlement'
        );

        if (! $approvalFlow) {
            $this->autoApprove($settlement);

            return;
        }

        $steps = $approvalFlow->steps()
            ->where('is_active', true)
            ->orderBy('sequence')
            ->get();

        if ($steps->isEmpty()) {
            $this->autoApprove($settlement);

            return;
        }

        $approvalRequest = CashAdvanceSettlementApprovalRequest::create([
            'cash_advance_settlement_id' => $settlement->id,
            'employee_id' => $employee->id,
            'approval_flow_id' => $approvalFlow->id,
            'status' => CashAdvanceSettlementApprovalRequestStatus::Pending->value,
            'current_step_sequence' => $steps->first()->sequence,
            'requested_at' => now(),
        ]);

        foreach ($steps as $step) {
            CashAdvanceSettlementApprovalStepDecision::create([
                'cash_advance_settlement_approval_request_id' => $approvalRequest->id,
                'approval_step_id' => $step->id,
                'sequence' => $step->sequence,
                'status' => CashAdvanceSettlementApprovalStepDecisionStatus::Pending->value,
            ]);
        }
    }

    public function autoApprove(CashAdvanceSettlement $settlement): void
    {
        $this->applyApproval($settlement, null);
    }

    public function decide(
        CashAdvanceSettlementApprovalStepDecision $decision,
        User $actor,
        string $action,
        ?string $notes,
    ): CashAdvanceSettlementApprovalRequest {
        $approvalRequest = $decision->request;
        $settlement = $approvalRequest->settlement;

        if ($settlement->status !== CashAdvanceSettlementStatus::Pending) {
            throw new CashAdvanceApprovalException('Settlement ini sudah tidak pending.');
        }

        if ($approvalRequest->status !== CashAdvanceSettlementApprovalRequestStatus::Pending) {
            throw new CashAdvanceApprovalException('Approval request settlement ini sudah tidak pending.');
        }

        if ($decision->sequence !== $approvalRequest->current_step_sequence) {
            throw new CashAdvanceApprovalException('Bukan giliran step ini untuk diputuskan.');
        }

        if ($decision->status !== CashAdvanceSettlementApprovalStepDecisionStatus::Pending) {
            throw new CashAdvanceApprovalException('Step ini sudah diputuskan sebelumnya.');
        }

        $eligibleUserIds = $this->resolver->resolveApproverUserIds($decision->approvalStep, $approvalRequest->employee);

        if (! in_array($actor->id, $eligibleUserIds, true)) {
            throw new CashAdvanceApprovalException('Anda tidak berwenang memutuskan approval ini.');
        }

        return DB::transaction(function () use ($decision, $actor, $action, $notes, $approvalRequest, $settlement) {
            if ($action === 'reject') {
                $decision->update([
                    'status' => CashAdvanceSettlementApprovalStepDecisionStatus::Rejected->value,
                    'decided_by_user_id' => $actor->id,
                    'notes' => $notes,
                    'decided_at' => now(),
                ]);

                $approvalRequest->update([
                    'status' => CashAdvanceSettlementApprovalRequestStatus::Rejected->value,
                    'decided_at' => now(),
                ]);

                $settlement->update([
                    'status' => CashAdvanceSettlementStatus::Rejected->value,
                    'rejected_at' => now(),
                ]);

                // Balik ke Need Settlement supaya employee bisa submit ulang.
                $settlement->request->update(['status' => CashAdvanceRequestStatus::NeedSettlement->value]);

                return $approvalRequest->fresh();
            }

            $decision->update([
                'status' => CashAdvanceSettlementApprovalStepDecisionStatus::Approved->value,
                'decided_by_user_id' => $actor->id,
                'notes' => $notes,
                'decided_at' => now(),
            ]);

            $nextStep = CashAdvanceSettlementApprovalStepDecision::where('cash_advance_settlement_approval_request_id', $approvalRequest->id)
                ->where('sequence', '>', $decision->sequence)
                ->orderBy('sequence')
                ->first();

            if (! $nextStep) {
                $approvalRequest->update([
                    'status' => CashAdvanceSettlementApprovalRequestStatus::Approved->value,
                    'decided_at' => now(),
                ]);

                $this->applyApproval($settlement, $actor);
            } else {
                $approvalRequest->update(['current_step_sequence' => $nextStep->sequence]);
            }

            return $approvalRequest->fresh();
        });
    }

    /**
     * @return array<int, CashAdvanceSettlementApprovalStepDecision>
     */
    public function pendingDecisionsForUser(User $user): array
    {
        $decisions = CashAdvanceSettlementApprovalStepDecision::query()
            ->where('status', CashAdvanceSettlementApprovalStepDecisionStatus::Pending->value)
            ->whereHas('request', fn ($query) => $query->where('status', CashAdvanceSettlementApprovalRequestStatus::Pending->value))
            ->with(['approvalStep', 'request.employee', 'request.settlement.items.category', 'request.settlement.request'])
            ->get()
            ->filter(fn (CashAdvanceSettlementApprovalStepDecision $decision) => $decision->sequence === $decision->request->current_step_sequence)
            ->filter(function (CashAdvanceSettlementApprovalStepDecision $decision) use ($user) {
                $eligibleUserIds = $this->resolver->resolveApproverUserIds($decision->approvalStep, $decision->request->employee);

                return in_array($user->id, $eligibleUserIds, true);
            });

        return $decisions->values()->all();
    }

    private function applyApproval(CashAdvanceSettlement $settlement, ?User $verifier): void
    {
        $settlement->update([
            'status' => CashAdvanceSettlementStatus::Approved->value,
            'approved_at' => now(),
            'verified_at' => now(),
            'verified_by_user_id' => $verifier?->id,
        ]);

        $settlement->request->update(['status' => CashAdvanceRequestStatus::Completed->value]);
    }
}