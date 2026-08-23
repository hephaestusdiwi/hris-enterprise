<?php

namespace App\Modules\Payroll\Enums;

enum PayrollApprovalRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    // Request jadi obsolete karena payroll run-nya direvisi ulang
    // (recalculate) atau dibatalkan SAAT request ini masih pending — bukan
    // ditolak oleh approver mana pun. Dipakai PayrollApprovalService::
    // cancelApprovalIfAny() supaya request lama tidak bisa lagi diputuskan
    // dan tidak salah tercatat sebagai "Rejected" (yang berarti keputusan
    // manusia) di riwayat approval.
    case Superseded = 'superseded';
}