<?php

namespace App\Modules\Loan\Enums;

enum LoanApprovalRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}