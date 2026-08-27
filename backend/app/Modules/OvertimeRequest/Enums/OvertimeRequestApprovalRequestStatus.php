<?php

namespace App\Modules\OvertimeRequest\Enums;

enum OvertimeRequestApprovalRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}