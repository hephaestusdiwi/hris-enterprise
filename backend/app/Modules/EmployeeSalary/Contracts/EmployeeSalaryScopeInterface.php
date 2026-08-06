<?php

namespace App\Modules\EmployeeSalary\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

interface EmployeeSalaryScopeInterface
{
    /**
     * Persempit query EmployeeSalary sesuai siapa yang login.
     * Dipakai khusus untuk endpoint list (index) — bukan single record.
     *
     * @param  Builder<\App\Modules\EmployeeSalary\Models\EmployeeSalary>  $query
     * @return Builder<\App\Modules\EmployeeSalary\Models\EmployeeSalary>
     */
    public function apply(Builder $query, User $user): Builder;
}