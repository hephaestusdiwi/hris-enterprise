<?php

namespace App\Modules\Screening\Policies;

use App\Models\User;
use App\Modules\Screening\Models\Screening;

class ScreeningPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view screenings');
    }

    public function view(User $user, Screening $screening): bool
    {
        return $this->isReviewer($user, $screening)
            || $user->can('view screenings');
    }

    public function create(User $user): bool
    {
        return $user->can('create screenings');
    }

    public function decide(User $user, Screening $screening): bool
    {
        return $this->isReviewer($user, $screening)
            || $user->can('decide screenings');
    }

    private function isReviewer(User $user, Screening $screening): bool
    {
        return (bool) $user->employee
            && $user->employee->id === $screening->reviewer_employee_id;
    }
}