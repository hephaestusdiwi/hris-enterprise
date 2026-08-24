<?php

namespace App\Modules\Expense\Services;

use App\Modules\ApprovalFlow\Services\ApprovalFlowResolver;
use App\Modules\Expense\Enums\ExpenseClaimApprovalRequestStatus;
use App\Modules\Expense\Enums\ExpenseClaimApprovalStepDecisionStatus;
use App\Modules\Expense\Enums\ExpenseClaimStatus;
use App\Modules\Expense\Models\ExpenseClaim;
use App\Modules\Expense\Models\ExpenseClaimApprovalRequest;
use App\Modules\Expense\Models\ExpenseClaimApprovalStepDecision;

/**
 * STEP 4A cuma butuh initiate()+autoApprove()+cancelApprovalIfAny() --
 * decide() (approve/reject step decision) adalah STEP 4C, sengaja belum
 * dibuat supaya claim yang masuk approval flow beneran akan tetap Pending
 * sampai STEP 4C mengerjakan layer decide-nya. Pola persis
 * ReimbursementApprovalService, TIDAK menyentuh ApprovalFlowResolver.
 */
class ExpenseClaimApprovalService
{
    public function __construct(
        private ApprovalFlowResolver $approvalFlowResolver,
    ) {
    }

    public function initiate(ExpenseClaim $claim): void
    {
        $employee = $claim->employee;

        $approvalFlow = $this->approvalFlowResolver->resolveFor(
            $employee,
            'expense_claim'
        );

        if (! $approvalFlow) {
            $this->autoApprove($claim);

            return;
        }

        $steps = $approvalFlow->steps()
            ->where('is_active', true)
            ->orderBy('sequence')
            ->get();

        if ($steps->isEmpty()) {
            $this->autoApprove($claim);

            return;
        }

        $approvalRequest = ExpenseClaimApprovalRequest::create([
            'expense_claim_id' => $claim->id,
            'employee_id' => $employee->id,
            'approval_flow_id' => $approvalFlow->id,
            'status' => ExpenseClaimApprovalRequestStatus::Pending->value,
            'current_step_sequence' => $steps->first()->sequence,
            'requested_at' => now(),
        ]);

        foreach ($steps as $step) {
            ExpenseClaimApprovalStepDecision::create([
                'expense_claim_approval_request_id' => $approvalRequest->id,
                'approval_step_id' => $step->id,
                'sequence' => $step->sequence,
                'status' => ExpenseClaimApprovalStepDecisionStatus::Pending->value,
            ]);
        }
    }

    public function autoApprove(ExpenseClaim $claim): void
    {
        $claim->update([
            'status' => ExpenseClaimStatus::Approved->value,
            'decided_at' => now(),
        ]);
    }

    public function cancelApprovalIfAny(ExpenseClaim $claim): void
    {
        $approvalRequest = $claim->approvalRequest;

        if ($approvalRequest && $approvalRequest->status === ExpenseClaimApprovalRequestStatus::Pending) {
            $approvalRequest->update([
                'decided_at' => now(),
            ]);
        }
    }
}