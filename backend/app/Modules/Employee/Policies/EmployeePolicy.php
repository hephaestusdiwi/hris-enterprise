<?php

namespace App\Modules\Employee\Policies;

use App\Models\User;
use App\Modules\Employee\Models\Employee;

/**
 * Object-level authorization untuk record Employee tunggal.
 *
 * RBAC (permission Spatie) tetap jadi gerbang pertama — Policy ini cuma
 * mempersempit RECORD MANA yang boleh diakses setelah RBAC lolos, bukan
 * menggantikan RBAC.
 *
 * Cakupan saat ini: self-view + direct manager melihat subordinate langsung.
 * Belum ada: department scope, company scope, recursive subordinate
 * (manager-nya-manager). Kalau nanti dibutuhkan, tinggal tambah branch baru
 * di method masing-masing — tidak perlu ubah Controller atau route.
 */
class EmployeePolicy
{
    public function view(User $user, Employee $employee): bool
    {
        if (! $user->can('view employees')) {
            return false;
        }

        if ($user->hasRole(['admin', 'hr'])) {
            return true;
        }

        $actingEmployee = $user->employee;

        if (! $actingEmployee) {
            return false;
        }

        return $actingEmployee->id === $employee->id
            || $actingEmployee->id === $employee->manager_employee_id;
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->hasRole(['admin', 'hr'])
            && $user->can('edit employees');
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->hasRole(['admin', 'hr'])
            && $user->can('delete employees');
    }
}