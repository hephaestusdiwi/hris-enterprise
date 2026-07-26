<?php

namespace App\Modules\LeaveBalance\Services;

use App\Modules\Employee\Models\Employee;
use App\Modules\LeaveType\Models\LeaveType;
use Carbon\Carbon;

class LeaveBalanceEligibilityService
{
    public function isEligible(Employee $employee, LeaveType $leaveType, Carbon $asOfDate): bool
    {
        if (! $leaveType->is_active || ! $leaveType->requires_balance) {
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