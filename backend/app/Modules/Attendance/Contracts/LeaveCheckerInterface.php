<?php

namespace App\Modules\Attendance\Contracts;

use Carbon\Carbon;

interface LeaveCheckerInterface
{
    public function isOnLeave(int $employeeId, Carbon $date): bool;
}