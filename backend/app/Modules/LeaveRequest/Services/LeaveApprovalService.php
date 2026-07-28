<?php

namespace App\Modules\LeaveRequest\Services;

use App\Models\User;
use App\Modules\ApprovalFlow\Models\ApprovalFlowAssignment;
use App\Modules\Attendance\Services\ApprovalStepApproverResolver;
use App\Modules\LeaveBalance\Support\LeaveBalanceMath;
use App\Modules\LeaveRequest\Enums\LeaveApprovalRequestStatus;
use App\Modules\LeaveRequest\Enums\LeaveApprovalStepDecisionStatus;
use App\Modules\LeaveRequest\Enums\LeaveRequestStatus;
use App\Modules\LeaveRequest\Exceptions\LeaveApprovalException;
use App\Modules\LeaveRequest\Models\LeaveApprovalRequest;
use App\Modules\LeaveRequest\Models\LeaveApprovalStepDecision;
use App\Modules\LeaveRequest\Models\LeaveRequest;

class LeaveApprovalService
{
    public function __construct(private ApprovalStepApproverResolver $resolver)
    {
    }

    public function initiate(LeaveRequest $leaveRequest): void
    {
        $employee = $leaveRequest->employee;

        $assignment = ApprovalFlowAssignment::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->first();

        if (! $assignment) {
            $this->autoApprove($leaveRequest);

            return;
        }

        $steps = $assignment->approvalFlow->steps()->where('is_active', true)->orderBy('sequence')->get();

        if ($steps->isEmpty()) {
            $this->autoApprove($leaveRequest);

            return;
        }

        $request = LeaveApprovalRequest::create([
            'leave_request_id' => $leaveRequest->id,
            'employee_id' => $employee->id,
            'approval_flow_id' => $assignment->approval_flow_id,
            'status' => LeaveApprovalRequestStatus::Pending->value,
            'current_step_sequence' => $steps->first()->sequence,
            'requested_at' => now(),
        ]);

        foreach ($steps as $step) {
            LeaveApprovalStepDecision::create([
                'leave_approval_request_id' => $request->id,
                'approval_step_id' => $step->id,
                'sequence' => $step->sequence,
                'status' => LeaveApprovalStepDecisionStatus::Pending->value,
            ]);
        }
    }

    public function autoApprove(LeaveRequest $leaveRequest): void
    {
        $this->applyApproval($leaveRequest);
    }

    public function cancelApprovalIfAny(LeaveRequest $leaveRequest): void
    {
        $request = $leaveRequest->approvalRequest;

        if ($request && $request->status === LeaveApprovalRequestStatus::Pending) {
            $request->update(['decided_at' => now()]);
        }
    }

    public function decide(
        LeaveApprovalStepDecision $decision,
        User $actor,
        string $action,
        ?string $notes,
    ): LeaveApprovalRequest {
        $request = $decision->request;

        if ($request->leaveRequest->status !== LeaveRequestStatus::Pending) {
            throw new LeaveApprovalException('Leave request ini sudah tidak pending (mungkin sudah dibatalkan).');
        }

        if ($request->status !== LeaveApprovalRequestStatus::Pending) {
            throw new LeaveApprovalException('Request ini sudah tidak pending.');
        }

        if ($decision->sequence !== $request->current_step_sequence) {
            throw new LeaveApprovalException('Bukan giliran step ini untuk diputuskan.');
        }

        if ($decision->status !== LeaveApprovalStepDecisionStatus::Pending) {
            throw new LeaveApprovalException('Step ini sudah diputuskan sebelumnya.');
        }

        $eligibleUserIds = $this->resolver->resolveApproverUserIds($decision->approvalStep, $request->employee);

        if (! in_array($actor->id, $eligibleUserIds, true)) {
            throw new LeaveApprovalException('Anda tidak berwenang memutuskan approval ini.');
        }

        if ($action === 'reject') {
            $decision->update([
                'status' => LeaveApprovalStepDecisionStatus::Rejected->value,
                'decided_by_user_id' => $actor->id,
                'notes' => $notes,
                'decided_at' => now(),
            ]);

            $request->update([
                'status' => LeaveApprovalRequestStatus::Rejected->value,
                'decided_at' => now(),
            ]);

            $request->leaveRequest->update([
                'status' => LeaveRequestStatus::Rejected->value,
                'decided_at' => now(),
            ]);

            return $request->fresh();
        }

        $decision->update([
            'status' => LeaveApprovalStepDecisionStatus::Approved->value,
            'decided_by_user_id' => $actor->id,
            'notes' => $notes,
            'decided_at' => now(),
        ]);

        $nextStep = LeaveApprovalStepDecision::where('leave_approval_request_id', $request->id)
            ->where('sequence', '>', $decision->sequence)
            ->orderBy('sequence')
            ->first();

        if (! $nextStep) {
            $request->update([
                'status' => LeaveApprovalRequestStatus::Approved->value,
                'decided_at' => now(),
            ]);

            $this->applyApproval($request->leaveRequest);
        } else {
            $request->update(['current_step_sequence' => $nextStep->sequence]);
        }

        return $request->fresh();
    }

    /**
     * @return array<int, LeaveApprovalStepDecision>
     */
    public function pendingDecisionsForUser(User $user): array
    {
        $decisions = LeaveApprovalStepDecision::query()
            ->where('status', LeaveApprovalStepDecisionStatus::Pending->value)
            ->whereHas('request', fn ($query) => $query->where('status', LeaveApprovalRequestStatus::Pending->value))
            ->with(['approvalStep', 'request.employee', 'request.leaveRequest.leaveType'])
            ->get()
            ->filter(fn (LeaveApprovalStepDecision $decision) => $decision->sequence === $decision->request->current_step_sequence)
            ->filter(function (LeaveApprovalStepDecision $decision) use ($user) {
                $eligibleUserIds = $this->resolver->resolveApproverUserIds($decision->approvalStep, $decision->request->employee);

                return in_array($user->id, $eligibleUserIds, true);
            });

        return $decisions->values()->all();
    }

    private function applyApproval(LeaveRequest $leaveRequest): void
    {
        $leaveRequest->update([
            'status' => LeaveRequestStatus::Approved->value,
            'decided_at' => now(),
        ]);

        if ($leaveRequest->leave_balance_id) {
            $balance = $leaveRequest->leaveBalance;
            $newUsedDays = LeaveBalanceMath::add((string) $balance->used_days, (string) $leaveRequest->total_days);
            $balance->update(['used_days' => $newUsedDays]);
        }
    }
}