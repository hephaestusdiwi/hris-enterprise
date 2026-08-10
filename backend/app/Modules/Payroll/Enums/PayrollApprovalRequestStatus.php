<?php

namespace App\Modules\Payroll\Enums;

enum PayrollApprovalRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}