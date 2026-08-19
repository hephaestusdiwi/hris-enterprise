<?php

namespace App\Modules\CashAdvance\Enums;

enum CashAdvanceApprovalRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}