<?php

namespace App\Modules\LeaveBalance\Strategies;

use App\Modules\LeaveBalance\Contracts\LeaveQuotaProrationStrategyInterface;
use App\Modules\LeaveType\Models\LeaveType;
use Carbon\Carbon;

class FullQuotaStrategy implements LeaveQuotaProrationStrategyInterface
{
    public function calculateQuota(LeaveType $leaveType, Carbon $periodStart, Carbon $periodEnd, Carbon $effectiveStart): ?string
    {
        if ($leaveType->max_days_per_year === null) {
            return null;
        }

        return number_format((float) $leaveType->max_days_per_year, 2, '.', '');
    }
}