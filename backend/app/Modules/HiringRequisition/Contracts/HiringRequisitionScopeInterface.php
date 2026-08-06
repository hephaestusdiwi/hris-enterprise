<?php

namespace App\Modules\HiringRequisition\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;


interface HiringRequisitionScopeInterface
{
    /**
     * Persempit query HiringRequisition sesuai siapa yang login.
     * Dipakai khusus untuk endpoint list (index) — bukan single record.
     *
     * @param  Builder<\App\Modules\HiringRequisition\Models\HiringRequisition>  $query
     * @return Builder<\App\Modules\HiringRequisition\Models\HiringRequisition>
     */
    public function apply(Builder $query, User $user): Builder;
}