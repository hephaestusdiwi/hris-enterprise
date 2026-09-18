<?php

namespace App\Modules\Grooming\Policies;

use App\Models\User;

class GroomingStandardPolicy
{
    public function manage(User $user): bool
    {
        return $user->can('manage grooming standards');
    }
}