<?php

namespace App\Modules\Employee\Services;

use App\Models\User;
use App\Modules\Employee\Contracts\EmployeeHierarchyServiceInterface;
use App\Modules\Employee\Contracts\EmployeeScopeInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Satu-satunya tempat aturan "siapa boleh lihat employee mana" untuk endpoint
 * list. Controller tidak perlu tahu detail aturannya, cukup panggil apply().
 *
 * Non-admin/hr: diri sendiri + SELURUH subordinate tree (bukan cuma direct
 * report lagi — lewat EmployeeHierarchyService, konsisten dengan
 * EmployeePolicy::view() supaya list dan single-record tidak bertentangan).
 *
 * Growth path (belum diimplementasikan, tinggal tambah branch di sini kalau
 * dibutuhkan — tanpa ubah Controller):
 * - HR discope per company/branch/department (saat ini hr masih full-visibility
 *   seperti admin).
 */
class EmployeeScope implements EmployeeScopeInterface
{
    public function __construct(private EmployeeHierarchyServiceInterface $hierarchy)
    {
    }

    public function apply(Builder $query, User $user): Builder
    {
        if ($user->hasRole(['admin', 'hr'])) {
            return $query;
        }

        $employee = $user->employee;

        if (! $employee) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id', $this->hierarchy->visibleEmployeeIds($employee));
    }
}
