<?php

namespace App\Modules\CashAdvance\Enums;

enum CashAdvanceSettlementApprovalRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}