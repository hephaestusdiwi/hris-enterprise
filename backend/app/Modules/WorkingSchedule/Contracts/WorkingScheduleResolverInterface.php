<?php

namespace App\Modules\WorkingSchedule\Contracts;

use App\Modules\Employee\Models\Employee;

interface WorkingScheduleResolverInterface
{
    public function resolveWorkingScheduleId(Employee $employee): ?int;
}