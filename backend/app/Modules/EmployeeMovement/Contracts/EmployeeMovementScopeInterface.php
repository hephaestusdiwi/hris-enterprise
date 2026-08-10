<?php

namespace App\Modules\EmployeeMovement\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

interface EmployeeMovementScopeInterface
{
    /**
     * @param  Builder<\App\Modules\EmployeeMovement\Models\EmployeeMovement>  $query
     * @return Builder<\App\Modules\EmployeeMovement\Models\EmployeeMovement>
     */
    public function apply(Builder $query, User $user): Builder;
}
