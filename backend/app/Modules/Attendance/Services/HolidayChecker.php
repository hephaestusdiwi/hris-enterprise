<?php

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Contracts\HolidayCheckerInterface;
use App\Modules\Holiday\Models\Holiday;
use Carbon\Carbon;

class HolidayChecker implements HolidayCheckerInterface
{
    public function isHoliday(int $companyId, ?int $branchId, Carbon $date): bool
    {
        return Holiday::where('company_id', $companyId)
            ->where('date', $date->toDateString())
            ->where('is_active', true)
            ->exists();
    }
}