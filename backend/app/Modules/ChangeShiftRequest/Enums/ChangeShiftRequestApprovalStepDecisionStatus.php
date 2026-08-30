<?php

namespace App\Modules\ChangeShiftRequest\Enums;

enum ChangeShiftRequestApprovalStepDecisionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}