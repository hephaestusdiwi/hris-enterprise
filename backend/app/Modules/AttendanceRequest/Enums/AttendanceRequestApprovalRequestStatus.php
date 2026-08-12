<?php

namespace App\Modules\AttendanceRequest\Enums;

enum AttendanceRequestApprovalRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
