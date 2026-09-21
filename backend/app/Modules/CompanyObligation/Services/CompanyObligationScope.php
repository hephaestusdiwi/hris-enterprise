<?php

namespace App\Modules\CompanyObligation\Services;

use App\Models\User;
use App\Modules\CompanyObligation\Contracts\CompanyObligationScopeInterface;
use App\Modules\CompanyObligation\Enums\CompanyObligationRecipientType;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cermin dari CompanyObligationPolicy::view() tapi bentuk QUERY (bukan
 * cek 1 record) -- prinsip sama seperti HiringRequisitionScope/
 * EmployeeScope: kalau Policy bilang "boleh lihat", Scope index harus
 * ikut menyertakan record itu, supaya list & single-record endpoint tidak
 * saling bertentangan.
 */
class CompanyObligationScope implements CompanyObligationScopeInterface
{
    public function applyMine(Builder $query, User $user): Builder
    {
        $employeeId = $user->employee?->id;
        $roleNames = $user->getRoleNames();

        return $query->where(function (Builder $q) use ($user, $employeeId, $roleNames) {
            if ($employeeId) {
                $q->orWhere('pic_employee_id', $employeeId);
            }

            $q->orWhereHas('recipients', function (Builder $r) use ($user, $roleNames) {
                $r->where(function (Builder $r2) use ($user, $roleNames) {
                    $r2->where('recipient_type', CompanyObligationRecipientType::User)
                        ->where('user_id', $user->id);
                })->orWhere(function (Builder $r2) use ($roleNames) {
                    $r2->where('recipient_type', CompanyObligationRecipientType::Role)
                        ->whereIn('role', $roleNames);
                });
            });
        });
    }
}