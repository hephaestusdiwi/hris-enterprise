<?php

namespace App\Modules\HiringRequisition\Services;

use App\Models\User;
use App\Modules\HiringRequisition\Contracts\HiringRequisitionScopeInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cermin dari HiringRequisitionPolicy: requester cuma lihat requisition
 * miliknya sendiri di list. HR/Admin lihat semua.
 */
class HiringRequisitionScope implements HiringRequisitionScopeInterface
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

        return $query->where('requested_by_employee_id', $employee->id);
    }
}