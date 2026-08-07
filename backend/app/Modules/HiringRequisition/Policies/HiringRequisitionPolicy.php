<?php

namespace App\Modules\HiringRequisition\Policies;

use App\Models\User;
use App\Modules\HiringRequisition\Models\HiringRequisition;

class HiringRequisitionPolicy
{
    public function create(User $user): bool
    {
        return $user->can('create hiring requisitions');
    }

    public function view(User $user, HiringRequisition $hiringRequisition): bool
    {
        return $this->isOwner($user, $hiringRequisition)
            || $user->can('view hiring requisitions');
    }

    public function update(User $user, HiringRequisition $hiringRequisition): bool
    {
        return $this->isOwner($user, $hiringRequisition)
            || $user->can('edit hiring requisitions');
    }

    public function cancel(User $user, HiringRequisition $hiringRequisition): bool
    {
        return $this->isOwner($user, $hiringRequisition)
            || $user->can('cancel hiring requisitions');
    }

    public function viewAny(User $user): bool
    {
        return $user->can('view hiring requisitions');
    }

    private function isOwner(User $user, HiringRequisition $hiringRequisition): bool
    {
        return (bool) $user->employee
            && $user->employee->id === $hiringRequisition->requested_by_employee_id;
    }
}