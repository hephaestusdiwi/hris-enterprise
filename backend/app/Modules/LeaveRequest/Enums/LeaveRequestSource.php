<?php

namespace App\Modules\LeaveRequest\Enums;

enum LeaveRequestSource: string
{
    case SelfSubmitted = 'self_submitted';
    case AbsenceDeduction = 'absence_deduction';

    public function label(): string
    {
        return match ($this) {
            self::SelfSubmitted => 'Self Submitted',
            self::AbsenceDeduction => 'Absence Deduction',
        };
    }
}