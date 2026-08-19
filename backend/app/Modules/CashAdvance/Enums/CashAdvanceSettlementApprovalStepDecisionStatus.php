<?php

namespace App\Modules\CashAdvance\Enums;

enum CashAdvanceSettlementApprovalStepDecisionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}