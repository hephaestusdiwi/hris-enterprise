<?php

namespace App\Modules\AttendanceRequest\Enums;

enum AttendanceRequestApprovalStepDecisionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
