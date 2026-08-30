<?php

namespace App\Modules\ChangeShiftRequest\Enums;

enum ChangeShiftRequestApprovalRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}