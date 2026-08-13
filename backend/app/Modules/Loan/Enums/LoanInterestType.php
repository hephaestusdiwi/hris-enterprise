<?php

namespace App\Modules\Loan\Enums;

enum LoanInterestType: string
{
    case None = 'none';
    case Flat = 'flat';
    case Declining = 'declining';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Tanpa Bunga',
            self::Flat => 'Flat',
            self::Declining => 'Menurun (Declining Balance)',
        };
    }
}