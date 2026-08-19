<?php

namespace App\Modules\CashAdvance\Enums;

enum CashAdvanceSettlementStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Verifikasi',
            self::Approved => 'Approved',
            self::Rejected => 'Ditolak',
        };
    }
}