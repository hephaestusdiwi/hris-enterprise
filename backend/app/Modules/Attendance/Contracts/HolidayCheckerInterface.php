<?php

namespace App\Modules\Attendance\Contracts;

use Carbon\Carbon;

interface HolidayCheckerInterface
{
    public function isHoliday(int $companyId, ?int $branchId, Carbon $date): bool;
}