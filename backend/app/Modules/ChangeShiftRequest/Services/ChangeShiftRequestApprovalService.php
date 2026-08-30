<?php

namespace App\Modules\ChangeShiftRequest\Services;

use App\Models\User;
use App\Modules\ApprovalFlow\Services\ApprovalFlowResolver;
use App\Modules\Attendance\Enums\AttendanceActivityType;
use App\Modules\Attendance\Services\ApprovalStepApproverResolver;
use App\Modules\Attendance\Services\AttendanceActivityService;
use App\Modules\ChangeShiftRequest\Enums\ChangeShiftRequestApprovalRequestStatus;
use App\Modules\ChangeShiftRequest\Enums\ChangeShiftRequestApprovalStepDecisionStatus;
use App\Modules\ChangeShiftRequest\Enums\ChangeShiftRequestStatus;
use App\Modules\ChangeShiftRequest\Exceptions\ChangeShiftRequestApprovalException;
use App\Modules\ChangeShiftRequest\Models\ChangeShiftRequest;
use App\Modules\ChangeShiftRequest\Models\ChangeShiftRequestApprovalRequest;
use App\Modules\ChangeShiftRequest\Models\ChangeShiftRequestApprovalStepDecision;

class ChangeShiftRequestApprovalService
{
    public function __construct(
        private ApprovalStepApproverResolver $resolver,
        private ApprovalFlowResolver $approvalFlowResolver,
        private AttendanceActivityService $activityService,
    ) {
    }

    public function initiate(ChangeShiftRequest $changeShiftRequest): void
    {
        $employee = $changeShiftRequest->employee;
        $approvalFlow = $this->approvalFlowResolver->resolveFor($employee, 'change_shift_request');

        if (! $approvalFlow) {
            $this->applyApproval($changeShiftRequest);

            return;
        }

        $steps = $approvalFlow->steps()->where('is_active', true)->orderBy('sequence')->get();

        if ($steps->isEmpty()) {
            $this->applyApproval($changeShiftRequest);

            return;
        }

        $request = ChangeShiftRequestApprovalRequest::create([
            'change_shift_request_id' => $changeShiftRequest->id,
            'employee_id' => $employee->id,
            'approval_flow_id' => $approvalFlow->id,
            'status' => ChangeShiftRequestApprovalRequestStatus::Pending->value,
            'current_step_sequence' => $steps->first()->sequence,
            'requested_at' => now(),
        ]);

        foreach ($steps as $step) {
            ChangeShiftRequestApprovalStepDecision::create([
                'change_shift_request_approval_request_id' => $request->id,
                'approval_step_id' => $step->id,
                'sequence' => $step->sequence,
                'status' => ChangeShiftRequestApprovalStepDecisionStatus::Pending->value,
            ]);
        }
    }

    public function cancelApprovalIfAny(ChangeShiftRequest $changeShiftRequest): void
    {
        $request = $changeShiftRequest->approvalRequest;

        if ($request && $request->status === ChangeShiftRequestApprovalRequestStatus::Pending) {
            $request->update(['decided_at' => now()]);
        }
    }

    public function decide(
        ChangeShiftRequestApprovalStepDecision $decision,
        User $actor,
        string $action,
        ?string $notes,
    ): ChangeShiftRequestApprovalRequest {
        $request = $decision->request;

        if ($request->changeShiftRequest->status !== ChangeShiftRequestStatus::Pending) {
            throw new ChangeShiftRequestApprovalException('Change shift request ini sudah tidak pending (mungkin sudah dibatalkan).');
        }

        if ($request->status !== ChangeShiftRequestApprovalRequestStatus::Pending) {
            throw new ChangeShiftRequestApprovalException('Request ini sudah tidak pending.');
        }

        if ($decision->sequence !== $request->current_step_sequence) {
            throw new ChangeShiftRequestApprovalException('Bukan giliran step ini untuk diputuskan.');
        }

        if ($decision->status !== ChangeShiftRequestApprovalStepDecisionStatus::Pending) {
            throw new ChangeShiftRequestApprovalException('Step ini sudah diputuskan sebelumnya.');
        }

        $eligibleUserIds = $this->resolver->resolveApproverUserIds($decision->approvalStep, $request->employee);

        if (! in_array($actor->id, $eligibleUserIds, true)) {
            throw new ChangeShiftRequestApprovalException('Anda tidak berwenang memutuskan approval ini.');
        }

        if ($action === 'reject') {
            $decision->update([
                'status' => ChangeShiftRequestApprovalStepDecisionStatus::Rejected->value,
                'decided_by_user_id' => $actor->id,
                'notes' => $notes,
                'decided_at' => now(),
            ]);

            $request->update([
                'status' => ChangeShiftRequestApprovalRequestStatus::Rejected->value,
                'decided_at' => now(),
            ]);

            $request->changeShiftRequest->update([
                'status' => ChangeShiftRequestStatus::Rejected->value,
                'decided_at' => now(),
            ]);

            $this->activityService->record(
                employeeId: $request->employee_id,
                type: AttendanceActivityType::ChangeShiftRequestRejected,
                actorUserId: $actor->id,
                metadata: ['notes' => $notes],
            );

            return $request->fresh();
        }

        $decision->update([
            'status' => ChangeShiftRequestApprovalStepDecisionStatus::Approved->value,
            'decided_by_user_id' => $actor->id,
            'notes' => $notes,
            'decided_at' => now(),
        ]);

        $nextStep = ChangeShiftRequestApprovalStepDecision::where('change_shift_request_approval_request_id', $request->id)
            ->where('sequence', '>', $decision->sequence)
            ->orderBy('sequence')
            ->first();

        if (! $nextStep) {
            $request->update([
                'status' => ChangeShiftRequestApprovalRequestStatus::Approved->value,
                'decided_at' => now(),
            ]);

            $this->applyApproval($request->changeShiftRequest);

            $this->activityService->record(
                employeeId: $request->employee_id,
                type: AttendanceActivityType::ChangeShiftRequestApproved,
                actorUserId: $actor->id,
                metadata: ['notes' => $notes],
            );
        } else {
            $request->update(['current_step_sequence' => $nextStep->sequence]);
        }

        return $request->fresh();
    }

    /**
     * @return array<int, ChangeShiftRequestApprovalStepDecision>
     */
    public function pendingDecisionsForUser(User $user): array
    {
        $decisions = ChangeShiftRequestApprovalStepDecision::query()
            ->where('status', ChangeShiftRequestApprovalStepDecisionStatus::Pending->value)
            ->whereHas('request', fn ($query) => $query->where('status', ChangeShiftRequestApprovalRequestStatus::Pending->value))
            ->with(['approvalStep', 'request.employee', 'request.changeShiftRequest.requestedShift', 'request.changeShiftRequest.currentShift'])
            ->get()
            ->filter(fn (ChangeShiftRequestApprovalStepDecision $decision) => $decision->sequence === $decision->request->current_step_sequence)
            ->filter(function (ChangeShiftRequestApprovalStepDecision $decision) use ($user) {
                $eligibleUserIds = $this->resolver->resolveApproverUserIds($decision->approvalStep, $decision->request->employee);

                return in_array($user->id, $eligibleUserIds, true);
            });

        return $decisions->values()->all();
    }

    private function applyApproval(ChangeShiftRequest $changeShiftRequest): void
    {
        $changeShiftRequest->update([
            'status' => ChangeShiftRequestStatus::Approved->value,
            'decided_at' => now(),
        ]);
    }
}