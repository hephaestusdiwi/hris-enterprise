<?php

namespace App\Modules\Reimbursement\Enums;

enum ReimbursementBalanceTransactionType: string
{
    case Initial = 'initial';
    case Claim = 'claim';
    case CancelReversal = 'cancel_reversal';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Assign Awal',
            self::Claim => 'Klaim (Approved)',
            self::CancelReversal => 'Reversal Pembatalan',
        };
    }
}