<?php

namespace App\Modules\OvertimeRequest\Services;

use App\Modules\Attendance\Enums\AttendanceActivityType;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Attendance\Services\AttendanceActivityService;
use App\Modules\Employee\Models\Employee;
use App\Modules\OvertimeRequest\Enums\OvertimeRequestStatus;
use App\Modules\OvertimeRequest\Exceptions\OvertimeRequestValidationException;
use App\Modules\OvertimeRequest\Models\OvertimeRequest;
use App\Modules\WorkingSchedule\Contracts\WorkingScheduleResolverInterface;
use App\Modules\WorkingSchedule\Models\WorkingScheduleDetail;
use Carbon\Carbon;

class OvertimeRequestService
{
    public function __construct(
        private WorkingScheduleResolverInterface $workingScheduleResolver,
        private OvertimeRequestApprovalService $approvalService,
        private AttendanceActivityService $activityService,
    ) {
    }

    /**
     * @param array{attendance_date: string, planned_minutes: int, reason: string} $data
     */
    public function submit(Employee $employee, array $data): OvertimeRequest
    {
        $attendanceDate = Carbon::parse($data['attendance_date'])->startOfDay();

        $this->assertNoPendingDuplicate($employee, $attendanceDate);

        // Sama seperti AttendanceRequest -- overtime cuma masuk akal relatif
        // ke shift normal employee. Kalau gak ada shift, gak ada "overtime"
        // yang bisa direncanakan.
        if (! $this->resolveShiftForDate($employee, $attendanceDate)) {
            throw new OvertimeRequestValidationException(
                'Employee tidak memiliki shift pada tanggal ini, overtime request tidak dapat diajukan.'
            );
        }

        $overtimeRequest = OvertimeRequest::create([
            'employee_id' => $employee->id,
            'attendance_date' => $attendanceDate->toDateString(),
            'planned_minutes' => $data['planned_minutes'],
            'reason' => $data['reason'],
            'status' => OvertimeRequestStatus::Pending->value,
            'requested_at' => now(),
        ]);

        $this->activityService->record(
            employeeId: $employee->id,
            type: AttendanceActivityType::OvertimeRequestSubmitted,
            actorUserId: $employee->user_id,
            metadata: [
                'attendance_date' => $overtimeRequest->attendance_date->toDateString(),
                'planned_minutes' => $data['planned_minutes'],
                'reason' => $data['reason'],
            ],
        );

        $this->approvalService->initiate($overtimeRequest);

        return $overtimeRequest->fresh();
    }

    public function cancel(OvertimeRequest $overtimeRequest): OvertimeRequest
    {
        if ($overtimeRequest->status !== OvertimeRequestStatus::Pending) {
            throw new OvertimeRequestValidationException('Hanya overtime request berstatus pending yang bisa dibatalkan.');
        }

        $overtimeRequest->update([
            'status' => OvertimeRequestStatus::Cancelled->value,
            'decided_at' => now(),
        ]);

        $this->approvalService->cancelApprovalIfAny($overtimeRequest);

        $this->activityService->record(
            employeeId: $overtimeRequest->employee_id,
            type: AttendanceActivityType::OvertimeRequestCancelled,
            actorUserId: $overtimeRequest->employee->user_id,
        );

        return $overtimeRequest->fresh();
    }

    /**
     * Fase "Claim" -- SETELAH tanggal overtime yang diminta beneran
     * kejadian & clock-out sudah tercatat. Ini SENGAJA self-service (tanpa
     * approval kedua) -- validasi "overtime beneran dikerjain" udah
     * dijamin lewat cek Attendance.detected_overtime_minutes yang datang
     * dari AttendanceCalculationEngine (STEP A/reuse penuh, bukan re-hitung
     * ulang). Kompensasi/eligibility pembayaran overtime itu sendiri tetap
     * domain Late/OT approval existing (AttendanceApprovalService) &
     * akhirnya Payroll (belum ada) -- claim di sini murni "employee
     * konfirmasi overtime yang direncanakan sudah dijalankan", bukan
     * mekanisme approval/mutasi finansial baru.
     */
    public function claim(OvertimeRequest $overtimeRequest): OvertimeRequest
    {
        if ($overtimeRequest->status !== OvertimeRequestStatus::Approved) {
            throw new OvertimeRequestValidationException('Hanya overtime request berstatus approved yang bisa di-claim.');
        }

        $attendance = Attendance::where('employee_id', $overtimeRequest->employee_id)
            ->where('attendance_date', $overtimeRequest->attendance_date->toDateString())
            ->first();

        if (! $attendance || ! $attendance->clock_out) {
            throw new OvertimeRequestValidationException(
                'Belum ada data clock-out untuk tanggal ini, overtime belum bisa di-claim.'
            );
        }

        if (! $attendance->detected_overtime_minutes || $attendance->detected_overtime_minutes <= 0) {
            throw new OvertimeRequestValidationException(
                'Tidak ada overtime yang terdeteksi dari data attendance pada tanggal ini.'
            );
        }

        $overtimeRequest->update([
            'status' => OvertimeRequestStatus::Claimed->value,
            'attendance_id' => $attendance->id,
            'actual_overtime_minutes' => $attendance->detected_overtime_minutes,
            'claimed_at' => now(),
        ]);

        $this->activityService->record(
            employeeId: $overtimeRequest->employee_id,
            type: AttendanceActivityType::OvertimeRequestClaimed,
            attendanceId: $attendance->id,
            actorUserId: $overtimeRequest->employee->user_id,
            metadata: [
                'planned_minutes' => $overtimeRequest->planned_minutes,
                'actual_overtime_minutes' => $attendance->detected_overtime_minutes,
            ],
        );

        return $overtimeRequest->fresh(['attendance']);
    }

    private function assertNoPendingDuplicate(Employee $employee, Carbon $attendanceDate): void
    {
        $exists = OvertimeRequest::where('employee_id', $employee->id)
            ->where('attendance_date', $attendanceDate->toDateString())
            ->whereIn('status', [OvertimeRequestStatus::Pending->value, OvertimeRequestStatus::Approved->value])
            ->exists();

        if ($exists) {
            throw new OvertimeRequestValidationException('Sudah ada overtime request pending/approved untuk tanggal ini.');
        }
    }

    /**
     * Duplikasi kecil dan sengaja dari AttendanceRequestService, sama
     * alasannya -- tidak menyentuh AttendanceService/AttendanceRequestService
     * supaya behavior existing tidak berubah sedikit pun.
     */
    private function resolveShiftForDate(Employee $employee, Carbon $date): bool
    {
        $workingScheduleId = $this->workingScheduleResolver->resolveWorkingScheduleId($employee);

        if (! $workingScheduleId) {
            return false;
        }

        return WorkingScheduleDetail::where('working_schedule_id', $workingScheduleId)
            ->where('day_of_week', $date->dayOfWeekIso)
            ->exists();
    }
}