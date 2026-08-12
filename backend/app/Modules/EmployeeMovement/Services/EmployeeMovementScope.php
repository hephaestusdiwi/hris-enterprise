<?php

namespace App\Modules\EmployeeMovement\Services;

use App\Models\User;
use App\Modules\Employee\Contracts\EmployeeHierarchyServiceInterface;
use App\Modules\EmployeeMovement\Contracts\EmployeeMovementScopeInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Visibility movement history mengikuti visibility Employee-nya sendiri:
 * admin/hr full, selain itu diri sendiri + seluruh subordinate tree
 * (konsisten dengan EmployeeScope, lewat EmployeeHierarchyService yang sama).
 */
class EmployeeMovementScope implements EmployeeMovementScopeInterface
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

        return $query->whereIn('employee_id', $this->hierarchy->visibleEmployeeIds($employee));
    }
}
