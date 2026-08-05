<?php

namespace App\Modules\Loan\Enums;

enum LoanStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Menunggu Approval',
            self::Approved => 'Approved',
            self::Rejected => 'Ditolak',
            self::Active => 'Active',
            self::Completed => 'Lunas',
            self::Cancelled => 'Dibatalkan',
        };
    }
}