<?php

namespace App\Modules\Reimbursement\Enums;

enum ReimbursementBalanceStatus: string
{
    case Active = 'active';
    case Stopped = 'stopped';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Stopped => 'Dihentikan',
        };
    }
}