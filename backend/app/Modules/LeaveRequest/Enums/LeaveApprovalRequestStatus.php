<?php

namespace App\Modules\LeaveRequest\Enums;

enum LeaveApprovalRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}