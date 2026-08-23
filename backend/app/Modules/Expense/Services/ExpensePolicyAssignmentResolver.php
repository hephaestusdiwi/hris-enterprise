<?php

namespace App\Modules\Expense\Services;

use App\Modules\Employee\Models\Employee;
use App\Modules\Expense\Models\ExpensePolicy;
use App\Modules\Expense\Models\ExpensePolicyAssignment;
use Carbon\Carbon;

/**
 * Dipakai internal untuk (nantinya) Expense Claim -- belum ada endpoint
 * publik untuk resolver ini di STEP 3 (sengaja ditunda sampai ada
 * consumer beneran). Pola resolveActiveAssignment() persis
 * EmployeeSalaryResolver::resolveActiveVersion().
 */
class ExpensePolicyAssignmentResolver
{
    public function resolveActiveAssignment(Employee $employee, Carbon $date): ?ExpensePolicyAssignment
    {
        return ExpensePolicyAssignment::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->where('effective_date', '<=', $date->toDateString())
            ->where(function ($query) use ($date) {
                $query->whereNull('expiration_date')
                    ->orWhere('expiration_date', '>=', $date->toDateString());
            })
            ->orderByDesc('effective_date')
            ->first();
    }

    /**
     * Assignment yang valid tanggalnya belum tentu policy-nya sendiri
     * masih efektif (bisa saja HR menonaktifkan/expire Policy-nya
     * terpisah) -- makanya isCurrentlyEffective() Policy dicek juga di
     * sini, bukan cuma isCurrentlyValid() milik assignment.
     */
    public function resolveActivePolicy(Employee $employee, Carbon $date): ?ExpensePolicy
    {
        $assignment = $this->resolveActiveAssignment($employee, $date);

        if (! $assignment || ! $assignment->policy->isCurrentlyEffective()) {
            return null;
        }

        return $assignment->policy;
    }
}