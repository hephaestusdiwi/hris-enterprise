<?php

namespace App\Modules\LeaveBalance\Services;

use App\Modules\Employee\Models\Employee;
use App\Modules\LeaveType\Models\LeaveType;
use Carbon\Carbon;

class LeaveBalanceEligibilityService
{
    public function isEligible(Employee $employee, LeaveType $leaveType, Carbon $asOfDate): bool
    {
        if (! $leaveType->requires_balance) {
            return false;
        }

        return $this->meetsServiceRequirement($employee, $leaveType, $asOfDate);
    }

    /**
     * Cek kelayakan employee terhadap leave type (masa kerja, gender, status aktif),
     * TANPA peduli apakah leave type itu requires_balance atau tidak.
     * Dipakai Leave Request (yang tetap perlu jalan untuk leave type non-balance
     * seperti Sick Leave), sedangkan isEligible() dipakai khusus Leave Balance Generation.
     */
    public function meetsServiceRequirement(Employee $employee, LeaveType $leaveType, Carbon $asOfDate): bool
    {
        if (! $leaveType->is_active) {
            return false;
        }

        if ($employee->resign_date && $employee->resign_date->lt($asOfDate)) {
            return false;
        }

        if ($leaveType->gender_restriction && $leaveType->gender_restriction->value !== $employee->gender) {
            return false;
        }

        $eligibleFrom = $this->resolveEligibleFrom($employee, $leaveType);

        return $eligibleFrom->lte($asOfDate);
    }

    public function resolveEligibleFrom(Employee $employee, LeaveType $leaveType): Carbon
    {
        $joinDate = Carbon::parse($employee->join_date);

        if ($leaveType->min_service_months <= 0) {
            return $joinDate;
        }

        return $joinDate->copy()->addMonths($leaveType->min_service_months);
    }
}