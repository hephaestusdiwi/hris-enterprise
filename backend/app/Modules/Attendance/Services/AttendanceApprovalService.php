<?php

namespace App\Modules\Attendance\Services;

use App\Models\User;
use App\Modules\ApprovalFlow\Services\ApprovalFlowResolver;
use App\Modules\Attendance\Enums\ApprovalMode;
use App\Modules\Attendance\Enums\AttendanceActivityType;
use App\Modules\Attendance\Enums\AttendanceApprovalRequestStatus;
use App\Modules\Attendance\Enums\AttendanceApprovalRequestType;
use App\Modules\Attendance\Enums\AttendanceApprovalStepDecisionStatus;
use App\Modules\Attendance\Exceptions\AttendanceApprovalException;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Attendance\Models\AttendanceApprovalRequest;
use App\Modules\Attendance\Models\AttendanceApprovalStepDecision;
use App\Modules\AttendanceSetting\Models\AttendanceSetting;
use App\Modules\Employee\Models\Employee;

class AttendanceApprovalService
{
    public function __construct(
        private ApprovalStepApproverResolver $resolver,
        private ApprovalFlowResolver $approvalFlowResolver,
        private AttendanceActivityService $activityService,
    ) {
    }

    public function handleLateDetected(Attendance $attendance, int $lateMinutes): void
    {
        $this->handleDetection($attendance, AttendanceApprovalRequestType::Late, $lateMinutes);
    }

    public function handleOvertimeDetected(Attendance $attendance, int $overtimeMinutes): void
    {
        $this->handleDetection($attendance, AttendanceApprovalRequestType::Overtime, $overtimeMinutes);
    }

    private function handleDetection(Attendance $attendance, AttendanceApprovalRequestType $type, int $detectedValue): void
    {
        $employee = $attendance->employee;

        $this->activityService->record(
            employeeId: $employee->id,
            type: $type === AttendanceApprovalRequestType::Late
                ? AttendanceActivityType::LateDetected
                : AttendanceActivityType::OvertimeDetected,
            attendanceId: $attendance->id,
            metadata: ['detected_value' => $detectedValue],
        );

        $mode = $this->resolveApprovalMode($employee);

        if ($mode === ApprovalMode::Disabled) {
            $this->applyApprovedValue($attendance, $type, $detectedValue);

            return;
        }

        if ($mode === ApprovalMode::Manual) {
            // Belum diimplementasikan: menunggu employee/HR submit request secara manual.
            // Sengaja tidak melakukan apa pun di sini sampai endpoint manual submission dibangun.
            return;
        }

        $approvalFlow = $this->approvalFlowResolver->resolveFor(
            $employee,
            'attendance'
        );

        if (! $approvalFlow) {
            $this->applyApprovedValue($attendance, $type, $detectedValue);

            return;
        }

        $steps = $approvalFlow->steps()->where('is_active', true)->orderBy('sequence')->get();

        if ($steps->isEmpty()) {
            $this->applyApprovedValue($attendance, $type, $detectedValue);

            return;
        }

        $request = AttendanceApprovalRequest::create([
            'attendance_id' => $attendance->id,
            'employee_id' => $employee->id,
            'approval_flow_id' => $approvalFlow->id,
            'type' => $type->value,
            'status' => AttendanceApprovalRequestStatus::Pending->value,
            'current_step_sequence' => $steps->first()->sequence,
            'detected_value' => $detectedValue,
            'working_value' => $detectedValue,
            'requested_at' => now(),
        ]);

        foreach ($steps as $step) {
            AttendanceApprovalStepDecision::create([
                'attendance_approval_request_id' => $request->id,
                'approval_step_id' => $step->id,
                'sequence' => $step->sequence,
                'status' => AttendanceApprovalStepDecisionStatus::Pending->value,
            ]);
        }

        $this->activityService->record(
            employeeId: $employee->id,
            type: $type === AttendanceApprovalRequestType::Late
                ? AttendanceActivityType::LateApprovalSubmitted
                : AttendanceActivityType::OvertimeApprovalSubmitted,
            attendanceId: $attendance->id,
            metadata: ['detected_value' => $detectedValue, 'approval_flow_id' => $approvalFlow->id],
        );
    }

    public function decide(
        AttendanceApprovalStepDecision $decision,
        User $actor,
        string $action,
        ?int $adjustedValue,
        ?string $notes,
    ): AttendanceApprovalRequest {
        $request = $decision->request;

        if ($request->status !== AttendanceApprovalRequestStatus::Pending) {
            throw new AttendanceApprovalException('Request ini sudah tidak pending.');
        }

        if ($decision->sequence !== $request->current_step_sequence) {
            throw new AttendanceApprovalException('Bukan giliran step ini untuk diputuskan.');
        }

        if ($decision->status !== AttendanceApprovalStepDecisionStatus::Pending) {
            throw new AttendanceApprovalException('Step ini sudah diputuskan sebelumnya.');
        }

        $eligibleUserIds = $this->resolver->resolveApproverUserIds($decision->approvalStep, $request->employee);

        if (! in_array($actor->id, $eligibleUserIds, true)) {
            throw new AttendanceApprovalException('Anda tidak berwenang memutuskan approval ini.');
        }

        if ($action === 'reject') {
            $decision->update([
                'status' => AttendanceApprovalStepDecisionStatus::Rejected->value,
                'decided_by_user_id' => $actor->id,
                'notes' => $notes,
                'decided_at' => now(),
            ]);

            $request->update([
                'status' => AttendanceApprovalRequestStatus::Rejected->value,
                'decided_at' => now(),
            ]);

            $this->activityService->record(
                employeeId: $request->employee_id,
                type: $request->type === AttendanceApprovalRequestType::Late
                    ? AttendanceActivityType::LateRejected
                    : AttendanceActivityType::OvertimeRejected,
                attendanceId: $request->attendance_id,
                actorUserId: $actor->id,
                metadata: ['notes' => $notes],
            );

            return $request->fresh();
        }

        $newWorkingValue = $adjustedValue ?? $request->working_value;

        $decision->update([
            'status' => AttendanceApprovalStepDecisionStatus::Approved->value,
            'decided_by_user_id' => $actor->id,
            'adjusted_value' => $adjustedValue,
            'notes' => $notes,
            'decided_at' => now(),
        ]);

        $nextStep = AttendanceApprovalStepDecision::where('attendance_approval_request_id', $request->id)
            ->where('sequence', '>', $decision->sequence)
            ->orderBy('sequence')
            ->first();

        if (! $nextStep) {
            $request->update([
                'status' => AttendanceApprovalRequestStatus::Approved->value,
                'working_value' => $newWorkingValue,
                'approved_value' => $newWorkingValue,
                'decided_at' => now(),
            ]);

            $this->applyApprovedValue($request->attendance, $request->type, $newWorkingValue);

            $this->activityService->record(
                employeeId: $request->employee_id,
                type: $request->type === AttendanceApprovalRequestType::Late
                    ? AttendanceActivityType::LateApproved
                    : AttendanceActivityType::OvertimeApproved,
                attendanceId: $request->attendance_id,
                actorUserId: $actor->id,
                metadata: ['approved_value' => $newWorkingValue, 'adjusted_value' => $adjustedValue, 'notes' => $notes],
            );
        } else {
            $request->update([
                'working_value' => $newWorkingValue,
                'current_step_sequence' => $nextStep->sequence,
            ]);
        }

        return $request->fresh();
    }

    /**
     * @return array<int, AttendanceApprovalStepDecision>
     */
    public function pendingDecisionsForUser(User $user): array
    {
        $decisions = AttendanceApprovalStepDecision::query()
            ->where('status', AttendanceApprovalStepDecisionStatus::Pending->value)
            ->whereHas('request', fn ($query) => $query->where('status', AttendanceApprovalRequestStatus::Pending->value))
            ->with(['approvalStep', 'request.employee', 'request.attendance'])
            ->get()
            ->filter(fn (AttendanceApprovalStepDecision $decision) => $decision->sequence === $decision->request->current_step_sequence)
            ->filter(function (AttendanceApprovalStepDecision $decision) use ($user) {
                $eligibleUserIds = $this->resolver->resolveApproverUserIds($decision->approvalStep, $decision->request->employee);

                return in_array($user->id, $eligibleUserIds, true);
            });

        return $decisions->values()->all();
    }

    private function applyApprovedValue(Attendance $attendance, AttendanceApprovalRequestType $type, int $value): void
    {
        if ($type === AttendanceApprovalRequestType::Late) {
            $attendance->update(['approved_late_minutes' => $value]);
        } elseif ($type === AttendanceApprovalRequestType::Overtime) {
            $attendance->update(['approved_overtime_minutes' => $value]);
        }
    }

    private function resolveApprovalMode(Employee $employee): ApprovalMode
    {
        $setting = $this->resolveAttendanceSetting($employee);

        if (! $setting || ! $setting->approval_mode) {
            return ApprovalMode::Automatic;
        }

        return ApprovalMode::from($setting->approval_mode);
    }

    private function resolveAttendanceSetting(Employee $employee): ?AttendanceSetting
    {
        if ($employee->branch_id) {
            $branchSetting = AttendanceSetting::where('company_id', $employee->company_id)
                ->where('branch_id', $employee->branch_id)
                ->first();

            if ($branchSetting) {
                return $branchSetting;
            }
        }

        return AttendanceSetting::where('company_id', $employee->company_id)
            ->whereNull('branch_id')
            ->first();
    }
}