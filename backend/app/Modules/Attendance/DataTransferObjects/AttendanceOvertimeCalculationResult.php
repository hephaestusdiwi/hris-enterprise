<?php

namespace App\Modules\Attendance\DataTransferObjects;

final class AttendanceOvertimeCalculationResult
{
    public function __construct(
        public readonly ?int $detectedOvertimeMinutes,
    ) {
    }
}