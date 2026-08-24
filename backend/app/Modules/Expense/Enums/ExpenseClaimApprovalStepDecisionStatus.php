<?php

namespace App\Modules\Expense\Enums;

enum ExpenseClaimApprovalStepDecisionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}