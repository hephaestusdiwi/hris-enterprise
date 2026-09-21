<?php

namespace App\Modules\CompanyObligation\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

interface CompanyObligationScopeInterface
{
    /**
     * Persempit query CompanyObligation ke yang "relevan buat user ini"
     * (dia PIC-nya, atau eksplisit jadi recipient user/role) -- dipakai
     * khusus endpoint self-service (my-company-obligations), BUKAN
     * endpoint management yang sudah digerbangi permission
     * 'view company obligations'.
     *
     * @param  Builder<\App\Modules\CompanyObligation\Models\CompanyObligation>  $query
     * @return Builder<\App\Modules\CompanyObligation\Models\CompanyObligation>
     */
    public function applyMine(Builder $query, User $user): Builder;
}