<?php

namespace App\Modules\Payroll\Enums;

enum PayrollRunStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Processed = 'processed';
    case Locked = 'locked';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Menunggu Approval',
            self::Approved => 'Approved',
            self::Processed => 'Processed',
            self::Locked => 'Locked',
            self::Cancelled => 'Dibatalkan',
        };
    }
}