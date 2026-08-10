<?php

namespace App\Modules\Payroll\Enums;

enum PayrollApprovalStepDecisionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}