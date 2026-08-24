<?php

namespace App\Modules\Expense\Enums;

enum ExpenseClaimApprovalRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}