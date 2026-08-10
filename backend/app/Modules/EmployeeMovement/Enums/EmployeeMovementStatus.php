<?php

namespace App\Modules\EmployeeMovement\Enums;

enum EmployeeMovementStatus: string
{
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Applied = 'applied';
}
