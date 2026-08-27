<?php

namespace App\Modules\OvertimeRequest\Enums;

enum OvertimeRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Claimed = 'claimed';
}