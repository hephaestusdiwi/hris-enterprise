<?php

namespace App\Modules\Attendance\DataTransferObjects;

use App\Modules\Attendance\Enums\AttendanceStatus;

final class AttendanceCalculationResult
{
    public function __construct(
        public readonly ?int $lateMinutes,
        public readonly ?bool $withinGrace,
        public readonly AttendanceStatus $status,
    ) {
    }
}