<?php

namespace App\Modules\ChangeShiftRequest\Enums;

enum ChangeShiftRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}