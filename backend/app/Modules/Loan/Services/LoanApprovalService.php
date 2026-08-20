<?php

namespace App\Modules\Loan\Services;

use App\Models\User;
use App\Modules\ApprovalFlow\Services\ApprovalFlowResolver;
use App\Modules\Attendance\Services\ApprovalStepApproverResolver;
use App\Modules\Loan\Enums\LoanApprovalRequestStatus;
use App\Modules\Loan\Enums\LoanApprovalStepDecisionStatus;
use App\Modules\Loan\Enums\LoanStatus;
use App\Modules\Loan\Exceptions\LoanApprovalException;
use App\Modules\Loan\Models\Loan;
use App\Modules\Loan\Models\LoanApprovalRequest;
use App\Modules\Loan\Models\LoanApprovalStepDecision;

class LoanApprovalService
{
    public function __construct(
        private ApprovalStepApproverResolver $resolver,
        private ApprovalFlowResolver $approvalFlowResolver,
    ) {
    }

    public function initiate(Loan $loan): void
    {
        $employee = $loan->employee;

        $approvalFlow = $this->approvalFlowResolver->resolveFor(
            $employee,
            'loan'
        );

        if (! $approvalFlow) {
            $this->autoApprove($loan);

            return;
        }

        $steps = $approvalFlow->steps()->where('is_active', true)->orderBy('sequence')->get();

        if ($steps->isEmpty()) {
            $this->autoApprove($loan);

            return;
        }

        $request = LoanApprovalRequest::create([
            'loan_id' => $loan->id,
            'employee_id' => $employee->id,
            'approval_flow_id' => $approvalFlow->id,
            'status' => LoanApprovalRequestStatus::Pending->value,
            'current_step_sequence' => $steps->first()->sequence,
            'requested_at' => now(),
        ]);

        foreach ($steps as $step) {
            LoanApprovalStepDecision::create([
                'loan_approval_request_id' => $request->id,
                'approval_step_id' => $step->id,
                'sequence' => $step->sequence,
                'status' => LoanApprovalStepDecisionStatus::Pending->value,
            ]);
        }
    }

    public function autoApprove(Loan $loan): void
    {
        $this->applyApproval($loan);
    }

    public function cancelApprovalIfAny(Loan $loan): void
    {
        $request = $loan->approvalRequest;

        if ($request && $request->status === LoanApprovalRequestStatus::Pending) {
            $request->update(['decided_at' => now()]);
        }
    }

    public function decide(
        LoanApprovalStepDecision $decision,
        User $actor,
        string $action,
        ?string $notes,
    ): LoanApprovalRequest {
        $request = $decision->request;

        if ($request->loan->status !== LoanStatus::Pending) {
            throw new LoanApprovalException('Loan ini sudah tidak pending (mungkin sudah dibatalkan).');
        }

        if ($request->status !== LoanApprovalRequestStatus::Pending) {
            throw new LoanApprovalException('Request ini sudah tidak pending.');
        }

        if ($decision->sequence !== $request->current_step_sequence) {
            throw new LoanApprovalException('Bukan giliran step ini untuk diputuskan.');
        }

        if ($decision->status !== LoanApprovalStepDecisionStatus::Pending) {
            throw new LoanApprovalException('Step ini sudah diputuskan sebelumnya.');
        }

        $eligibleUserIds = $this->resolver->resolveApproverUserIds($decision->approvalStep, $request->employee);

        if (! in_array($actor->id, $eligibleUserIds, true)) {
            throw new LoanApprovalException('Anda tidak berwenang memutuskan approval ini.');
        }

        if ($action === 'reject') {
            $decision->update([
                'status' => LoanApprovalStepDecisionStatus::Rejected->value,
                'decided_by_user_id' => $actor->id,
                'notes' => $notes,
                'decided_at' => now(),
            ]);

            $request->update([
                'status' => LoanApprovalRequestStatus::Rejected->value,
                'decided_at' => now(),
            ]);

            $request->loan->update([
                'status' => LoanStatus::Rejected->value,
                'decided_at' => now(),
            ]);

            return $request->fresh();
        }

        $decision->update([
            'status' => LoanApprovalStepDecisionStatus::Approved->value,
            'decided_by_user_id' => $actor->id,
            'notes' => $notes,
            'decided_at' => now(),
        ]);

        $nextStep = LoanApprovalStepDecision::where('loan_approval_request_id', $request->id)
            ->where('sequence', '>', $decision->sequence)
            ->orderBy('sequence')
            ->first();

        if (! $nextStep) {
            $request->update([
                'status' => LoanApprovalRequestStatus::Approved->value,
                'decided_at' => now(),
            ]);

            $this->applyApproval($request->loan);
        } else {
            $request->update(['current_step_sequence' => $nextStep->sequence]);
        }

        return $request->fresh();
    }

    /**
     * @return array<int, LoanApprovalStepDecision>
     */
    public function pendingDecisionsForUser(User $user): array
    {
        $decisions = LoanApprovalStepDecision::query()
            ->where('status', LoanApprovalStepDecisionStatus::Pending->value)
            ->whereHas('request', fn ($query) => $query->where('status', LoanApprovalRequestStatus::Pending->value))
            ->with(['approvalStep', 'request.employee', 'request.loan'])
            ->get()
            ->filter(fn (LoanApprovalStepDecision $decision) => $decision->sequence === $decision->request->current_step_sequence)
            ->filter(function (LoanApprovalStepDecision $decision) use ($user) {
                $eligibleUserIds = $this->resolver->resolveApproverUserIds($decision->approvalStep, $decision->request->employee);

                return in_array($user->id, $eligibleUserIds, true);
            });

        return $decisions->values()->all();
    }

    private function applyApproval(Loan $loan): void
    {
        $loan->update([
            'status' => LoanStatus::Approved->value,
            'decided_at' => now(),
        ]);
    }
}