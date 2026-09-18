<?php

namespace App\Modules\Payroll\Enums;

enum PayrollRunType: string
{
    case Regular = 'regular';
    case Thr = 'thr';
    case NonRegular = 'non_regular';

    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Regular Payroll',
            self::Thr => 'THR',
            self::NonRegular => 'Non-Regular Payroll',
        };
    }
}