<?php

namespace App\Modules\Payroll\Enums;

enum PayrollApprovalStepDecisionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    // Lihat PayrollApprovalRequestStatus::Superseded — step decision ikut
    // di-superseded bareng request induknya kalau masih pending saat
    // request itu jadi obsolete.
    case Superseded = 'superseded';
}