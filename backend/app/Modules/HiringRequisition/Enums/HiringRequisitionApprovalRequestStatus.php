<?php

namespace App\Modules\HiringRequisition\Enums;

enum HiringRequisitionApprovalRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}