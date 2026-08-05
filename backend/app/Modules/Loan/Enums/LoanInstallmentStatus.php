<?php

namespace App\Modules\Loan\Enums;

enum LoanInstallmentStatus: string
{
    case Scheduled = 'scheduled';
    case Paid = 'paid';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Terjadwal',
            self::Paid => 'Terbayar',
            self::Skipped => 'Dilewati',
            self::Cancelled => 'Dibatalkan',
        };
    }
}