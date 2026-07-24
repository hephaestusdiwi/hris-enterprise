<?php

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Contracts\AttendanceCalculationEngineInterface;
use App\Modules\Attendance\Contracts\HolidayCheckerInterface;
use App\Modules\Attendance\Contracts\LeaveCheckerInterface;
use App\Modules\Attendance\DataTransferObjects\AttendanceCalculationResult;
use App\Modules\Attendance\DataTransferObjects\AttendanceOvertimeCalculationResult;
use App\Modules\Attendance\Enums\AttendanceStatus;
use App\Modules\Employee\Models\Employee;
use App\Modules\Shift\Models\Shift;
use Carbon\Carbon;

class AttendanceCalculationEngine implements AttendanceCalculationEngineInterface
{
    public function __construct(
        private HolidayCheckerInterface $holidayChecker,
        private LeaveCheckerInterface $leaveChecker,
    ) {
    }

    public function calculateClockIn(
        Employee $employee,
        Carbon $attendanceDate,
        Carbon $clockInAt,
        ?Shift $shift,
    ): AttendanceCalculationResult {
        if (! $shift) {
            return new AttendanceCalculationResult(null, null, AttendanceStatus::Present);
        }

        if ($this->holidayChecker->isHoliday($employee->company_id, $employee->branch_id, $attendanceDate)) {
            return new AttendanceCalculationResult(null, null, AttendanceStatus::Present);
        }

        if ($this->leaveChecker->isOnLeave($employee->id, $attendanceDate)) {
            return new AttendanceCalculationResult(null, null, AttendanceStatus::Leave);
        }

        $shiftStart = $attendanceDate->copy()->setTimeFromTimeString($shift->start_time);

        $lateSeconds = $clockInAt->getTimestamp() - $shiftStart->getTimestamp();
        $lateMinutes = max(0, intdiv($lateSeconds, 60));
        $withinGrace = $lateMinutes <= $shift->late_tolerance_minutes;

        $status = $withinGrace ? AttendanceStatus::Present : AttendanceStatus::Late;

        return new AttendanceCalculationResult($lateMinutes, $withinGrace, $status);
    }

    public function calculateClockOut(
        Employee $employee,
        Carbon $attendanceDate,
        Carbon $clockOutAt,
        ?Shift $shift,
    ): AttendanceOvertimeCalculationResult {
        if (! $shift) {
            return new AttendanceOvertimeCalculationResult(null);
        }

        $shiftEnd = $attendanceDate->copy()->setTimeFromTimeString($shift->end_time);

        if ($shift->is_overnight) {
            $shiftEnd->addDay();
        }

        $overtimeSeconds = $clockOutAt->getTimestamp() - $shiftEnd->getTimestamp();
        $overtimeMinutes = intdiv(max(0, $overtimeSeconds), 60);

        $detectedOvertimeMinutes = $overtimeMinutes > $shift->overtime_threshold_minutes
            ? $overtimeMinutes
            : null;

        return new AttendanceOvertimeCalculationResult($detectedOvertimeMinutes);
    }
}