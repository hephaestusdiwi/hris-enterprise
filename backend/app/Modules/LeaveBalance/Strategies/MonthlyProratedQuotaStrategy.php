<?php

namespace App\Modules\LeaveBalance\Strategies;

use App\Modules\LeaveBalance\Contracts\LeaveQuotaProrationStrategyInterface;
use App\Modules\LeaveBalance\Support\LeaveBalanceMath;
use App\Modules\LeaveType\Models\LeaveType;
use Carbon\Carbon;

class MonthlyProratedQuotaStrategy implements LeaveQuotaProrationStrategyInterface
{
    public function calculateQuota(LeaveType $leaveType, Carbon $periodStart, Carbon $periodEnd, Carbon $effectiveStart): ?string
    {
        if ($leaveType->max_days_per_year === null) {
            return null;
        }

        if ($effectiveStart->gt($periodEnd)) {
            return '0.00';
        }

        // Titik ini satu-satunya yang masih float murni — Carbon tidak punya mode BCMath untuk date-diff.
        $totalMonthsInPeriod = $periodStart->diffInMonths($periodEnd->copy()->addDay()) ?: 12;
        $monthsUsed = $periodStart->diffInMonths($effectiveStart);
        $monthsRemaining = max(0, $totalMonthsInPeriod - $monthsUsed);

        // Dari sini seterusnya, semua lewat BCMath.
        $fraction = LeaveBalanceMath::div((string) $monthsRemaining, (string) $totalMonthsInPeriod);

        return LeaveBalanceMath::mul((string) $leaveType->max_days_per_year, $fraction);
    }
}