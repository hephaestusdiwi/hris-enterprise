<?php

namespace App\Modules\EmployeeSalary\Policies;

use App\Models\User;
use App\Modules\EmployeeSalary\Models\EmployeeSalary;

/**
 * Object-level authorization untuk record EmployeeSalary tunggal.
 *
 * TIDAK berisi business rule payroll apapun (perhitungan, proration, dst) —
 * itu tetap di EmployeeSalaryResolver & Service. Policy ini cuma menjawab
 * "user ini boleh akses record gaji ini atau tidak".
 *
 * Beda sengaja dari EmployeePolicy: manager TIDAK otomatis melihat gaji
 * subordinate-nya. Data gaji lebih sensitif daripada data profil, jadi
 * defaultnya cuma diri sendiri + HR/admin.
 */
class EmployeeSalaryPolicy
{
    public function view(User $user, EmployeeSalary $employeeSalary): bool
    {
        if (! $user->can('view employee salaries')) {
            return false;
        }

        if ($user->hasRole(['admin', 'hr'])) {
            return true;
        }

        return (bool) $user->employee
            && $user->employee->id === $employeeSalary->employee_id;
    }

    public function delete(User $user, EmployeeSalary $employeeSalary): bool
    {
        return $user->hasRole(['admin', 'hr'])
            && $user->can('delete employee salaries');
    }
}