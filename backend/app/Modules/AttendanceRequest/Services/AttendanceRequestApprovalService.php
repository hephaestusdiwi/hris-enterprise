<?php

namespace App\Modules\AttendanceRequest\Services;

use App\Models\User;
use App\Modules\ApprovalFlow\Services\ApprovalFlowResolver;
use App\Modules\Attendance\Contracts\AttendanceCalculationEngineInterface;
use App\Modules\Attendance\Enums\AttendanceMethod;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Attendance\Services\ApprovalStepApproverResolver;
use App\Modules\AttendanceRequest\Enums\AttendanceRequestApprovalRequestStatus;
use App\Modules\AttendanceRequest\Enums\AttendanceRequestApprovalStepDecisionStatus;
use App\Modules\AttendanceRequest\Enums\AttendanceRequestStatus;
use App\Modules\AttendanceRequest\Exceptions\AttendanceRequestApprovalException;
use App\Modules\AttendanceRequest\Models\AttendanceRequest;
use App\Modules\AttendanceRequest\Models\AttendanceRequestApprovalRequest;
use App\Modules\AttendanceRequest\Models\AttendanceRequestApprovalStepDecision;

class AttendanceRequestApprovalService
{
    public function __construct(
        private ApprovalStepApproverResolver $resolver,
        private ApprovalFlowResolver $approvalFlowResolver,
        private AttendanceCalculationEngineInterface $calculationEngine,
    ) {
    }

    /**
     * Reuse ApprovalFlowResolver generic yang sama dipakai Leave/Attendance
     * (Late-OT)/Loan/HiringRequisition -- TIDAK bikin approval engine baru.
     * Kalau tidak ada flow yang cocok / flow tidak punya step aktif, mirror
     * behavior LeaveApprovalService: langsung auto-approve.
     */
    public function initiate(AttendanceRequest $attendanceRequest): void
    {
        $employee = $attendanceRequest->employee;
        $approvalFlow = $this->approvalFlowResolver->resolveFor(
            $employee,
            'attendance_request'
        );

        if (! $approvalFlow) {
            $this->applyApproval($attendanceRequest);

            return;
        }

        $steps = $approvalFlow->steps()->where('is_active', true)->orderBy('sequence')->get();

        if ($steps->isEmpty()) {
            $this->applyApproval($attendanceRequest);

            return;
        }

        $request = AttendanceRequestApprovalRequest::create([
            'attendance_request_id' => $attendanceRequest->id,
            'employee_id' => $employee->id,
            'approval_flow_id' => $approvalFlow->id,
            'status' => AttendanceRequestApprovalRequestStatus::Pending->value,
            'current_step_sequence' => $steps->first()->sequence,
            'requested_at' => now(),
        ]);

        foreach ($steps as $step) {
            AttendanceRequestApprovalStepDecision::create([
                'attendance_request_approval_request_id' => $request->id,
                'approval_step_id' => $step->id,
                'sequence' => $step->sequence,
                'status' => AttendanceRequestApprovalStepDecisionStatus::Pending->value,
            ]);
        }
    }

    public function cancelApprovalIfAny(AttendanceRequest $attendanceRequest): void
    {
        $request = $attendanceRequest->approvalRequest;

        if ($request && $request->status === AttendanceRequestApprovalRequestStatus::Pending) {
            $request->update(['decided_at' => now()]);
        }
    }

    public function decide(
        AttendanceRequestApprovalStepDecision $decision,
        User $actor,
        string $action,
        ?string $notes,
    ): AttendanceRequestApprovalRequest {
        $request = $decision->request;

        if ($request->attendanceRequest->status !== AttendanceRequestStatus::Pending) {
            throw new AttendanceRequestApprovalException('Attendance request ini sudah tidak pending (mungkin sudah dibatalkan).');
        }

        if ($request->status !== AttendanceRequestApprovalRequestStatus::Pending) {
            throw new AttendanceRequestApprovalException('Request ini sudah tidak pending.');
        }

        if ($decision->sequence !== $request->current_step_sequence) {
            throw new AttendanceRequestApprovalException('Bukan giliran step ini untuk diputuskan.');
        }

        if ($decision->status !== AttendanceRequestApprovalStepDecisionStatus::Pending) {
            throw new AttendanceRequestApprovalException('Step ini sudah diputuskan sebelumnya.');
        }

        $eligibleUserIds = $this->resolver->resolveApproverUserIds($decision->approvalStep, $request->employee);

        if (! in_array($actor->id, $eligibleUserIds, true)) {
            throw new AttendanceRequestApprovalException('Anda tidak berwenang memutuskan approval ini.');
        }

        if ($action === 'reject') {
            $decision->update([
                'status' => AttendanceRequestApprovalStepDecisionStatus::Rejected->value,
                'decided_by_user_id' => $actor->id,
                'notes' => $notes,
                'decided_at' => now(),
            ]);

            $request->update([
                'status' => AttendanceRequestApprovalRequestStatus::Rejected->value,
                'decided_at' => now(),
            ]);

            $request->attendanceRequest->update([
                'status' => AttendanceRequestStatus::Rejected->value,
                'decided_at' => now(),
            ]);

            return $request->fresh();
        }

        $decision->update([
            'status' => AttendanceRequestApprovalStepDecisionStatus::Approved->value,
            'decided_by_user_id' => $actor->id,
            'notes' => $notes,
            'decided_at' => now(),
        ]);

        $nextStep = AttendanceRequestApprovalStepDecision::where('attendance_request_approval_request_id', $request->id)
            ->where('sequence', '>', $decision->sequence)
            ->orderBy('sequence')
            ->first();

        if (! $nextStep) {
            $request->update([
                'status' => AttendanceRequestApprovalRequestStatus::Approved->value,
                'decided_at' => now(),
            ]);

            $this->applyApproval($request->attendanceRequest);
        } else {
            $request->update(['current_step_sequence' => $nextStep->sequence]);
        }

        return $request->fresh();
    }

    /**
     * @return array<int, AttendanceRequestApprovalStepDecision>
     */
    public function pendingDecisionsForUser(User $user): array
    {
        $decisions = AttendanceRequestApprovalStepDecision::query()
            ->where('status', AttendanceRequestApprovalStepDecisionStatus::Pending->value)
            ->whereHas('request', fn ($query) => $query->where('status', AttendanceRequestApprovalRequestStatus::Pending->value))
            ->with(['approvalStep', 'request.employee', 'request.attendanceRequest'])
            ->get()
            ->filter(fn (AttendanceRequestApprovalStepDecision $decision) => $decision->sequence === $decision->request->current_step_sequence)
            ->filter(function (AttendanceRequestApprovalStepDecision $decision) use ($user) {
                $eligibleUserIds = $this->resolver->resolveApproverUserIds($decision->approvalStep, $decision->request->employee);

                return in_array($user->id, $eligibleUserIds, true);
            });

        return $decisions->values()->all();
    }

    /**
     * Attendance Request adalah FINAL approval (keputusan eksplisit): begitu
     * approved, langsung apply ke Attendance dan recalculate late/overtime
     * lewat AttendanceCalculationEngine yang sudah ada (reuse, bukan
     * reimplement). SENGAJA TIDAK memanggil
     * AttendanceApprovalService::handleLateDetected/handleOvertimeDetected,
     * supaya approval AttendanceRequest tidak memicu approval Late/OT kedua.
     */
    private function applyApproval(AttendanceRequest $attendanceRequest): void
    {
        $attendanceRequest->update([
            'status' => AttendanceRequestStatus::Approved->value,
            'decided_at' => now(),
        ]);

        $employee = $attendanceRequest->employee;

        $attendance = $attendanceRequest->attendance ?: Attendance::firstOrNew([
            'employee_id' => $employee->id,
            'attendance_date' => $attendanceRequest->attendance_date->toDateString(),
        ]);

        // Shift dari attendance existing (hasil clock-in normal) tetap jadi
        // prioritas kalau ada; shift hasil resolve saat submit request cuma
        // dipakai kalau attendance memang belum punya shift sama sekali.
        $effectiveShift = ($attendance->exists && $attendance->shift_id) ? $attendance->shift : $attendanceRequest->shift;

        if ($attendanceRequest->requested_clock_in) {
            $calculation = $this->calculationEngine->calculateClockIn(
                $employee,
                $attendanceRequest->attendance_date,
                $attendanceRequest->requested_clock_in,
                $effectiveShift,
            );

            $attendance->shift_id = $effectiveShift?->id;
            $attendance->clock_in = $attendanceRequest->requested_clock_in;
            $attendance->clock_in_method = AttendanceMethod::AttendanceRequest->value;
            $attendance->late_minutes = $calculation->lateMinutes;
            $attendance->within_grace = $calculation->withinGrace;
            $attendance->status = $calculation->status;
        }

        if ($attendanceRequest->requested_clock_out) {
            $overtime = $this->calculationEngine->calculateClockOut(
                $employee,
                $attendanceRequest->attendance_date,
                $attendanceRequest->requested_clock_out,
                $effectiveShift,
            );

            $attendance->shift_id = $attendance->shift_id ?? $effectiveShift?->id;
            $attendance->clock_out = $attendanceRequest->requested_clock_out;
            $attendance->clock_out_method = AttendanceMethod::AttendanceRequest->value;
            $attendance->detected_overtime_minutes = $overtime->detectedOvertimeMinutes;
        }

        $attendance->save();

        if ($attendanceRequest->attendance_id !== $attendance->id) {
            $attendanceRequest->update(['attendance_id' => $attendance->id]);
        }
    }
}
