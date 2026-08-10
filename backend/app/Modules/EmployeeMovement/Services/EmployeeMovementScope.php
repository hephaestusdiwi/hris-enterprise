<?php

namespace App\Modules\EmployeeMovement\Services;

use App\Models\User;
use App\Modules\EmployeeMovement\Contracts\EmployeeMovementScopeInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pola identik EmployeeScope (Phase 1): admin/hr full, selain itu cuma
 * lihat movement milik diri sendiri + subordinate langsung.
 */
class EmployeeMovementScope implements EmployeeMovementScopeInterface
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

        return $query->whereHas('employee', function (Builder $q) use ($employee) {
            $q->where('id', $employee->id)->orWhere('manager_employee_id', $employee->id);
        });
    }
}
