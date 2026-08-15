<?php

namespace App\Modules\Offering\Policies;

use App\Models\User;
use App\Modules\Offering\Models\Offering;

class OfferingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view offerings');
    }

    public function view(User $user, Offering $offering): bool
    {
        return $this->isOwner($user, $offering) || $user->can('view offerings');
    }

    public function create(User $user): bool
    {
        return $user->can('create offerings');
    }

    public function update(User $user, Offering $offering): bool
    {
        return $this->isOwner($user, $offering) || $user->can('edit offerings');
    }

    public function send(User $user, Offering $offering): bool
    {
        return $this->isOwner($user, $offering) || $user->can('send offerings');
    }

    public function respond(User $user, Offering $offering): bool
    {
        return $this->isOwner($user, $offering) || $user->can('edit offerings');
    }

    public function withdraw(User $user, Offering $offering): bool
    {
        return $this->isOwner($user, $offering) || $user->can('edit offerings');
    }

    private function isOwner(User $user, Offering $offering): bool
    {
        $vacancy = $offering->candidate?->jobVacancy;

        return (bool) $user->employee
            && $vacancy
            && in_array($user->employee->id, [
                $vacancy->hiring_manager_employee_id,
                $vacancy->recruiter_employee_id,
            ], true);
    }
}