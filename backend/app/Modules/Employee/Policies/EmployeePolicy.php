<?php

namespace App\Modules\Employee\Policies;

use App\Models\User;
use App\Modules\Employee\Contracts\EmployeeHierarchyServiceInterface;
use App\Modules\Employee\Models\Employee;

/**
 * Object-level authorization untuk record Employee tunggal.
 *
 * RBAC (permission Spatie) tetap jadi gerbang pertama — Policy ini cuma
 * mempersempit RECORD MANA yang boleh diakses setelah RBAC lolos, bukan
 * menggantikan RBAC.
 *
 * Cakupan view(): self + SELURUH subordinate tree (via
 * EmployeeHierarchyService), konsisten dengan EmployeeScope supaya list
 * endpoint dan single-record endpoint tidak saling bertentangan (kalau
 * satu bilang "boleh lihat" harusnya yang lain juga bilang begitu).
 *
 * update()/delete() SENGAJA tetap HR/admin-only, tidak ikut meluas ke
 * hierarchy — edit/hapus data master employee bukan wewenang manager
 * setinggi apapun posisinya di tree, itu keputusan sadar dari awal
 * (bukan lupa), tetap dipertahankan di sini.
 */
class EmployeePolicy
{
    public function __construct(private EmployeeHierarchyServiceInterface $hierarchy)
    {
    }

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
            || $this->hierarchy->isInSubordinateTree($actingEmployee, $employee);
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
