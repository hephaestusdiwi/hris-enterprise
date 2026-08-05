<?php

namespace App\Modules\HiringRequisition\Enums;

enum HiringRequisitionApprovalStepDecisionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}