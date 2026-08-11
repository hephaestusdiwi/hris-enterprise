<?php

namespace App\Modules\AttendanceRequest\Enums;

enum AttendanceRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
 