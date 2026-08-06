<?php

namespace App\Modules\Employee\Services;

use App\Models\User;
use App\Modules\Employee\Contracts\EmployeeScopeInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Satu-satunya tempat aturan "siapa boleh lihat employee mana" untuk endpoint
 * list. Controller tidak perlu tahu detail aturannya, cukup panggil apply().
 *
 * Growth path (belum diimplementasikan, tinggal tambah branch di sini kalau
 * dibutuhkan — tanpa ubah Controller):
 * - HR discope per company/branch/department (saat ini hr masih full-visibility
 *   seperti admin).
 * - Subordinate rekursif (bawahan dari bawahan), saat ini cuma direct report.
 */
class EmployeeScope implements EmployeeScopeInterface
{
    public function apply(Builder $query, User $user): Builder
    {
        if ($user->hasRole(['admin', 'hr'])) {
            return $query;
        }

        $employee = $user->employee;

        if (! $employee) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($employee) {
            $q->where('id', $employee->id)
                ->orWhere('manager_employee_id', $employee->id);
        });
    }
}