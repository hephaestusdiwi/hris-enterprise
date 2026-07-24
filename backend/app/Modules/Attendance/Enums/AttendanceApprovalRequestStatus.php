<?php

namespace App\Modules\Attendance\Enums;

enum AttendanceApprovalRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}