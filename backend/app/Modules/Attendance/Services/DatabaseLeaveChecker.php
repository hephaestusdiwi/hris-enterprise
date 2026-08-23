<?php

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Contracts\LeaveCheckerInterface;
use App\Modules\LeaveRequest\Enums\LeaveRequestStatus;
use App\Modules\LeaveRequest\Models\LeaveRequest;
use Carbon\Carbon;

/**
 * Implementasi asli LeaveCheckerInterface, menggantikan NullLeaveChecker
 * (stub yang selalu return false). Reuse LeaveRequest existing sepenuhnya
 * -- tidak ada tabel/kolom baru, tidak mengubah module Leave.
 *
 * Employee/company isolation: LeaveRequest tidak punya kolom company_id
 * sendiri (konsisten dengan konvensi module lain di repo ini, mis.
 * AttendanceApprovalRequest) -- isolasi terjadi implisit lewat
 * employee_id, karena caller (AttendanceCalculationEngine /
 * AttendanceReportService) selalu memberikan $employeeId milik satu
 * employee tertentu.
 */
class DatabaseLeaveChecker implements LeaveCheckerInterface
{
    public function isOnLeave(int $employeeId, Carbon $date): bool
    {
        return LeaveRequest::where('employee_id', $employeeId)
            ->where('status', LeaveRequestStatus::Approved->value)
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->exists();
    }
}