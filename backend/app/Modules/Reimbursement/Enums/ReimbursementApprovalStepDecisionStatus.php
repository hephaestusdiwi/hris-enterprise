<?php

namespace App\Modules\Reimbursement\Enums;

enum ReimbursementApprovalStepDecisionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}