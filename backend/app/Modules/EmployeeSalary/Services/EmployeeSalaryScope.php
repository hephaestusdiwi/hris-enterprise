<?php

namespace App\Modules\EmployeeSalary\Services;

use App\Models\User;
use App\Modules\EmployeeSalary\Contracts\EmployeeSalaryScopeInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sengaja lebih ketat dari EmployeeScope: TIDAK ada subordinate visibility
 * di sini. Non-HR/admin cuma bisa lihat baris gajinya sendiri di list.
 *
 * Growth path (belum diimplementasikan): HR discope per company/branch.
 */
class EmployeeSalaryScope implements EmployeeSalaryScopeInterface
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

        return $query->where('employee_id', $employee->id);
    }
}