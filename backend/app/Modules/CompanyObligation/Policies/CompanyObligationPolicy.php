<?php

namespace App\Modules\CompanyObligation\Policies;

use App\Models\User;
use App\Modules\CompanyObligation\Enums\CompanyObligationRecipientType;
use App\Modules\CompanyObligation\Models\CompanyObligation;

/**
 * RBAC (permission Spatie) tetap gerbang pertama -- Policy ini cuma
 * mempersempit RECORD MANA yang boleh diakses setelah RBAC lolos, pola
 * persis EmployeePolicy/HiringRequisitionPolicy.
 *
 * view() diperluas ke PIC dan recipient eksplisit (type=user) obligation
 * ybs, supaya orang yang ditugaskan/di-notify tetap bisa buka detailnya
 * sendiri walau tidak dikasih permission 'view company obligations' penuh
 * (mis. role employee biasa yang kebetulan jadi PIC sewa ruko).
 *
 * create/update/delete SENGAJA tetap permission-only (tidak ikut meluas ke
 * PIC) -- PIC cuma "ditugaskan", bukan otomatis boleh ubah/hapus data
 * obligation-nya sendiri.
 */
class CompanyObligationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view company obligations');
    }

    public function view(User $user, CompanyObligation $obligation): bool
    {
        if ($user->can('view company obligations')) {
            return true;
        }

        if ($this->isPic($user, $obligation)) {
            return true;
        }

        return $obligation->recipients
            ->where('recipient_type', CompanyObligationRecipientType::User)
            ->contains('user_id', $user->id);
    }

    public function create(User $user): bool
    {
        return $user->can('create company obligations');
    }

    public function update(User $user, CompanyObligation $obligation): bool
    {
        return $user->can('edit company obligations');
    }

    public function delete(User $user, CompanyObligation $obligation): bool
    {
        return $user->can('delete company obligations');
    }

    private function isPic(User $user, CompanyObligation $obligation): bool
    {
        return (bool) $user->employee
            && $obligation->pic_employee_id !== null
            && $user->employee->id === $obligation->pic_employee_id;
    }
}