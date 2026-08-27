<?php

namespace App\Modules\OvertimeRequest\Enums;

enum OvertimeRequestApprovalStepDecisionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}