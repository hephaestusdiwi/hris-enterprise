<?php

namespace App\Modules\CashAdvance\Enums;

enum CashAdvanceApprovalStepDecisionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}