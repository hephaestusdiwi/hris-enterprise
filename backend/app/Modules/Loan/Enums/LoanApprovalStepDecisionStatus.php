<?php

namespace App\Modules\Loan\Enums;

enum LoanApprovalStepDecisionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}