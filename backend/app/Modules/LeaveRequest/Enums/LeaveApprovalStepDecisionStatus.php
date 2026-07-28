<?php

namespace App\Modules\LeaveRequest\Enums;

enum LeaveApprovalStepDecisionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}