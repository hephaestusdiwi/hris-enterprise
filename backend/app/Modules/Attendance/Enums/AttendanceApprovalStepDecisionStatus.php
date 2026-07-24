<?php

namespace App\Modules\Attendance\Enums;

enum AttendanceApprovalStepDecisionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}