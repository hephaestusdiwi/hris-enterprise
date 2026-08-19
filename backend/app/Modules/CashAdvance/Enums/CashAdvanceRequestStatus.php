<?php

namespace App\Modules\CashAdvance\Enums;

enum CashAdvanceRequestStatus: string
{
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case NeedSettlement = 'need_settlement';
    case SettlementOnReview = 'settlement_on_review';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::PendingApproval => 'Menunggu Approval',
            self::Approved => 'Approved',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
            self::NeedSettlement => 'Perlu Settlement',
            self::SettlementOnReview => 'Settlement Direview',
            self::Completed => 'Selesai',
        };
    }
}