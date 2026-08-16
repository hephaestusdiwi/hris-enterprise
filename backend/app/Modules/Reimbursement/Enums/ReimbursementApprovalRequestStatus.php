<?php

namespace App\Modules\Reimbursement\Enums;

enum ReimbursementApprovalRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}