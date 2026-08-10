<?php

namespace App\Modules\EmployeeMovement\Enums;

enum EmployeeMovementApprovalRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
