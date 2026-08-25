<?php

namespace App\Modules\Expense\Services;

use App\Models\User;
use App\Modules\ApprovalFlow\Services\ApprovalFlowResolver;
use App\Modules\Attendance\Services\ApprovalStepApproverResolver;
use App\Modules\Expense\Enums\ExpenseClaimApprovalRequestStatus;
use App\Modules\Expense\Enums\ExpenseClaimApprovalStepDecisionStatus;
use App\Modules\Expense\Enums\ExpenseClaimStatus;
use App\Modules\Expense\Exceptions\ExpenseClaimApprovalException;
use App\Modules\Expense\Models\ExpenseClaim;
use App\Modules\Expense\Models\ExpenseClaimApprovalRequest;
use App\Modules\Expense\Models\ExpenseClaimApprovalStepDecision;
use Illuminate\Support\Facades\DB;

/**
 * initiate()/autoApprove()/cancelApprovalIfAny() dari STEP 4A.
 * decide()/pendingDecisionsForUser() BARU di STEP 4C -- pola persis
 * CashAdvanceApprovalService::decide() (tidak ada balance deduction,
 * paling sederhana di antara Loan/Reimbursement/CashAdvance), TIDAK
 * menyentuh ApprovalFlowResolver/ApprovalStepApproverResolver.
 */
class ExpenseClaimApprovalService
{
    public function __construct(
        private ApprovalFlowResolver $approvalFlowResolver,
        private ApprovalStepApproverResolver $approverResolver,
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

    public function decide(
        ExpenseClaimApprovalStepDecision $decision,
        User $actor,
        string $action,
        ?string $notes,
    ): ExpenseClaimApprovalRequest {
        $approvalRequest = $decision->request;

        if ($approvalRequest->claim->status !== ExpenseClaimStatus::Pending) {
            throw new ExpenseClaimApprovalException('Claim ini sudah tidak berstatus pending approval.');
        }

        if ($approvalRequest->status !== ExpenseClaimApprovalRequestStatus::Pending) {
            throw new ExpenseClaimApprovalException('Approval request ini sudah tidak pending.');
        }

        if ($decision->sequence !== $approvalRequest->current_step_sequence) {
            throw new ExpenseClaimApprovalException('Bukan giliran step ini untuk diputuskan.');
        }

        if ($decision->status !== ExpenseClaimApprovalStepDecisionStatus::Pending) {
            throw new ExpenseClaimApprovalException('Step ini sudah diputuskan sebelumnya.');
        }

        $eligibleUserIds = $this->approverResolver->resolveApproverUserIds($decision->approvalStep, $approvalRequest->employee);

        if (! in_array($actor->id, $eligibleUserIds, true)) {
            throw new ExpenseClaimApprovalException('Anda tidak berwenang memutuskan approval ini.');
        }

        return DB::transaction(function () use ($decision, $actor, $action, $notes, $approvalRequest) {
            if ($action === 'reject') {
                $decision->update([
                    'status' => ExpenseClaimApprovalStepDecisionStatus::Rejected->value,
                    'decided_by_user_id' => $actor->id,
                    'notes' => $notes,
                    'decided_at' => now(),
                ]);

                $approvalRequest->update([
                    'status' => ExpenseClaimApprovalRequestStatus::Rejected->value,
                    'decided_at' => now(),
                ]);

                $approvalRequest->claim->update([
                    'status' => ExpenseClaimStatus::Rejected->value,
                    'decided_at' => now(),
                ]);

                return $approvalRequest->fresh();
            }

            $decision->update([
                'status' => ExpenseClaimApprovalStepDecisionStatus::Approved->value,
                'decided_by_user_id' => $actor->id,
                'notes' => $notes,
                'decided_at' => now(),
            ]);

            $nextStep = ExpenseClaimApprovalStepDecision::where('expense_claim_approval_request_id', $approvalRequest->id)
                ->where('sequence', '>', $decision->sequence)
                ->orderBy('sequence')
                ->first();

            if (! $nextStep) {
                $approvalRequest->update([
                    'status' => ExpenseClaimApprovalRequestStatus::Approved->value,
                    'decided_at' => now(),
                ]);

                $approvalRequest->claim->update([
                    'status' => ExpenseClaimStatus::Approved->value,
                    'decided_at' => now(),
                ]);
            } else {
                $approvalRequest->update(['current_step_sequence' => $nextStep->sequence]);
            }

            return $approvalRequest->fresh();
        });
    }

    /**
     * @return array<int, ExpenseClaimApprovalStepDecision>
     */
    public function pendingDecisionsForUser(User $user): array
    {
        $decisions = ExpenseClaimApprovalStepDecision::query()
            ->where('status', ExpenseClaimApprovalStepDecisionStatus::Pending->value)
            ->whereHas('request', fn ($query) => $query->where('status', ExpenseClaimApprovalRequestStatus::Pending->value))
            ->with(['approvalStep', 'request.employee', 'request.claim.category', 'request.claim.subcategory'])
            ->get()
            ->filter(fn (ExpenseClaimApprovalStepDecision $decision) => $decision->sequence === $decision->request->current_step_sequence)
            ->filter(function (ExpenseClaimApprovalStepDecision $decision) use ($user) {
                $eligibleUserIds = $this->approverResolver->resolveApproverUserIds($decision->approvalStep, $decision->request->employee);

                return in_array($user->id, $eligibleUserIds, true);
            });

        return $decisions->values()->all();
    }
}