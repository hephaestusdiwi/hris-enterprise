<?php

namespace App\Modules\NewJoiner\Policies;

use App\Models\User;
use App\Modules\NewJoiner\Models\NewJoiner;

class NewJoinerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view new joiners');
    }

    public function view(User $user, NewJoiner $newJoiner): bool
    {
        return $user->can('view new joiners');
    }

    public function create(User $user): bool
    {
        return $user->can('manage new joiners');
    }

    public function proceedAsEmployee(User $user): bool
    {
        return $user->can('proceed as employee');
    }
}