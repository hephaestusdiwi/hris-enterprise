<?php

namespace App\Modules\Attendance\Contracts;

use App\Modules\Attendance\DataTransferObjects\AttendanceCalculationResult;
use App\Modules\Attendance\DataTransferObjects\AttendanceOvertimeCalculationResult;
use App\Modules\Employee\Models\Employee;
use App\Modules\Shift\Models\Shift;
use Carbon\Carbon;

interface AttendanceCalculationEngineInterface
{
    public function calculateClockIn(
        Employee $employee,
        Carbon $attendanceDate,
        Carbon $clockInAt,
        ?Shift $shift,
    ): AttendanceCalculationResult;

    public function calculateClockOut(
        Employee $employee,
        Carbon $attendanceDate,
        Carbon $clockOutAt,
        ?Shift $shift,
    ): AttendanceOvertimeCalculationResult;
}