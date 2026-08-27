<?php

namespace App\Modules\OvertimeRequest\Services;

use App\Models\User;
use App\Modules\ApprovalFlow\Services\ApprovalFlowResolver;
use App\Modules\Attendance\Enums\AttendanceActivityType;
use App\Modules\Attendance\Services\ApprovalStepApproverResolver;
use App\Modules\Attendance\Services\AttendanceActivityService;
use App\Modules\OvertimeRequest\Enums\OvertimeRequestApprovalRequestStatus;
use App\Modules\OvertimeRequest\Enums\OvertimeRequestApprovalStepDecisionStatus;
use App\Modules\OvertimeRequest\Enums\OvertimeRequestStatus;
use App\Modules\OvertimeRequest\Exceptions\OvertimeRequestApprovalException;
use App\Modules\OvertimeRequest\Models\OvertimeRequest;
use App\Modules\OvertimeRequest\Models\OvertimeRequestApprovalRequest;
use App\Modules\OvertimeRequest\Models\OvertimeRequestApprovalStepDecision;

class OvertimeRequestApprovalService
{
    public function __construct(
        private ApprovalStepApproverResolver $resolver,
        private ApprovalFlowResolver $approvalFlowResolver,
        private AttendanceActivityService $activityService,
    ) {
    }

    /**
     * Reuse ApprovalFlowResolver generic yang sama dipakai Leave/Attendance/
     * AttendanceRequest -- approval_type BARU 'overtime_request' (bukan
     * bentrok sama 'attendance' yang dipakai Late/OT auto-detect existing).
     * Tanpa flow/step aktif -> auto-approve, sama persis pola
     * AttendanceRequestApprovalService/LeaveApprovalService.
     */
    public function initiate(OvertimeRequest $overtimeRequest): void
    {
        $employee = $overtimeRequest->employee;
        $approvalFlow = $this->approvalFlowResolver->resolveFor($employee, 'overtime_request');

        if (! $approvalFlow) {
            $this->applyApproval($overtimeRequest);

            return;
        }

        $steps = $approvalFlow->steps()->where('is_active', true)->orderBy('sequence')->get();

        if ($steps->isEmpty()) {
            $this->applyApproval($overtimeRequest);

            return;
        }

        $request = OvertimeRequestApprovalRequest::create([
            'overtime_request_id' => $overtimeRequest->id,
            'employee_id' => $employee->id,
            'approval_flow_id' => $approvalFlow->id,
            'status' => OvertimeRequestApprovalRequestStatus::Pending->value,
            'current_step_sequence' => $steps->first()->sequence,
            'requested_at' => now(),
        ]);

        foreach ($steps as $step) {
            OvertimeRequestApprovalStepDecision::create([
                'overtime_request_approval_request_id' => $request->id,
                'approval_step_id' => $step->id,
                'sequence' => $step->sequence,
                'status' => OvertimeRequestApprovalStepDecisionStatus::Pending->value,
            ]);
        }
    }

    public function cancelApprovalIfAny(OvertimeRequest $overtimeRequest): void
    {
        $request = $overtimeRequest->approvalRequest;

        if ($request && $request->status === OvertimeRequestApprovalRequestStatus::Pending) {
            $request->update(['decided_at' => now()]);
        }
    }

    public function decide(
        OvertimeRequestApprovalStepDecision $decision,
        User $actor,
        string $action,
        ?string $notes,
    ): OvertimeRequestApprovalRequest {
        $request = $decision->request;

        if ($request->overtimeRequest->status !== OvertimeRequestStatus::Pending) {
            throw new OvertimeRequestApprovalException('Overtime request ini sudah tidak pending (mungkin sudah dibatalkan).');
        }

        if ($request->status !== OvertimeRequestApprovalRequestStatus::Pending) {
            throw new OvertimeRequestApprovalException('Request ini sudah tidak pending.');
        }

        if ($decision->sequence !== $request->current_step_sequence) {
            throw new OvertimeRequestApprovalException('Bukan giliran step ini untuk diputuskan.');
        }

        if ($decision->status !== OvertimeRequestApprovalStepDecisionStatus::Pending) {
            throw new OvertimeRequestApprovalException('Step ini sudah diputuskan sebelumnya.');
        }

        $eligibleUserIds = $this->resolver->resolveApproverUserIds($decision->approvalStep, $request->employee);

        if (! in_array($actor->id, $eligibleUserIds, true)) {
            throw new OvertimeRequestApprovalException('Anda tidak berwenang memutuskan approval ini.');
        }

        if ($action === 'reject') {
            $decision->update([
                'status' => OvertimeRequestApprovalStepDecisionStatus::Rejected->value,
                'decided_by_user_id' => $actor->id,
                'notes' => $notes,
                'decided_at' => now(),
            ]);

            $request->update([
                'status' => OvertimeRequestApprovalRequestStatus::Rejected->value,
                'decided_at' => now(),
            ]);

            $request->overtimeRequest->update([
                'status' => OvertimeRequestStatus::Rejected->value,
                'decided_at' => now(),
            ]);

            $this->activityService->record(
                employeeId: $request->employee_id,
                type: AttendanceActivityType::OvertimeRequestRejected,
                actorUserId: $actor->id,
                metadata: ['notes' => $notes],
            );

            return $request->fresh();
        }

        $decision->update([
            'status' => OvertimeRequestApprovalStepDecisionStatus::Approved->value,
            'decided_by_user_id' => $actor->id,
            'notes' => $notes,
            'decided_at' => now(),
        ]);

        $nextStep = OvertimeRequestApprovalStepDecision::where('overtime_request_approval_request_id', $request->id)
            ->where('sequence', '>', $decision->sequence)
            ->orderBy('sequence')
            ->first();

        if (! $nextStep) {
            $request->update([
                'status' => OvertimeRequestApprovalRequestStatus::Approved->value,
                'decided_at' => now(),
            ]);

            $this->applyApproval($request->overtimeRequest);

            $this->activityService->record(
                employeeId: $request->employee_id,
                type: AttendanceActivityType::OvertimeRequestApproved,
                actorUserId: $actor->id,
                metadata: ['notes' => $notes],
            );
        } else {
            $request->update(['current_step_sequence' => $nextStep->sequence]);
        }

        return $request->fresh();
    }

    /**
     * @return array<int, OvertimeRequestApprovalStepDecision>
     */
    public function pendingDecisionsForUser(User $user): array
    {
        $decisions = OvertimeRequestApprovalStepDecision::query()
            ->where('status', OvertimeRequestApprovalStepDecisionStatus::Pending->value)
            ->whereHas('request', fn ($query) => $query->where('status', OvertimeRequestApprovalRequestStatus::Pending->value))
            ->with(['approvalStep', 'request.employee', 'request.overtimeRequest'])
            ->get()
            ->filter(fn (OvertimeRequestApprovalStepDecision $decision) => $decision->sequence === $decision->request->current_step_sequence)
            ->filter(function (OvertimeRequestApprovalStepDecision $decision) use ($user) {
                $eligibleUserIds = $this->resolver->resolveApproverUserIds($decision->approvalStep, $decision->request->employee);

                return in_array($user->id, $eligibleUserIds, true);
            });

        return $decisions->values()->all();
    }

    /**
     * Beda dari AttendanceRequestApprovalService::applyApproval() -- di
     * sini TIDAK ada mutasi Attendance apapun. Overtime Request cuma
     * "izin merencanakan lembur"; efek nyata (link ke Attendance +
     * actual_overtime_minutes) baru terjadi di fase claim()
     * (OvertimeRequestService::claim()), SETELAH overtime beneran
     * dikerjain & clock-out tercatat.
     */
    private function applyApproval(OvertimeRequest $overtimeRequest): void
    {
        $overtimeRequest->update([
            'status' => OvertimeRequestStatus::Approved->value,
            'decided_at' => now(),
        ]);
    }
}