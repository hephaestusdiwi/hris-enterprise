<?php

namespace App\Modules\Attendance\Contracts;

use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Employee\Models\Employee;

interface AttendanceIdentificationStrategyInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function identify(AttendanceDevice $device, array $payload): Employee;
}