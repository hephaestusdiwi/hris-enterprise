<?php

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Contracts\LeaveCheckerInterface;
use Carbon\Carbon;

/**
 * Stub sampai module Leave (PHASE 4) dibangun.
 * Nanti tinggal bikin implementasi asli (misal DatabaseLeaveChecker)
 * dan ganti binding-nya di AppServiceProvider — kelas ini dan
 * semua pemanggilnya (AttendanceCalculationEngine) tidak perlu diubah.
 */
class NullLeaveChecker implements LeaveCheckerInterface
{
    public function isOnLeave(int $employeeId, Carbon $date): bool
    {
        return false;
    }
}