<?php

namespace App\Modules\LeaveBalance\Contracts;

use App\Modules\LeaveType\Models\LeaveType;
use Carbon\Carbon;

interface LeaveQuotaProrationStrategyInterface
{
    public function calculateQuota(LeaveType $leaveType, Carbon $periodStart, Carbon $periodEnd, Carbon $effectiveStart): ?string;
}