<?php

namespace App\Modules\EmployeeMovement\Enums;

enum EmployeeMovementApprovalStepDecisionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
