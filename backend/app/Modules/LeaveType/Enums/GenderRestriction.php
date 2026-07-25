<?php

namespace App\Modules\LeaveType\Enums;

enum GenderRestriction: string
{
    case Male = 'male';
    case Female = 'female';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Khusus Laki-laki',
            self::Female => 'Khusus Perempuan',
        };
    }
}