<?php

namespace App\Modules\Employee\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

interface EmployeeScopeInterface
{
    public function apply(Builder $query, User $user): Builder;
}