<?php

namespace App\Modules\Pph21\Contracts;

use Carbon\Carbon;
use Illuminate\Support\Collection;

interface TaxBracketResolverInterface
{
    /**
     * Ambil SELURUH lapisan tarif Pasal 17 yang berlaku pada referenceDate,
     * terurut dari income_from terkecil — dipakai Pasal17Calculator buat
     * jalan progresif berlapis.
     *
     * @return Collection<int, \App\Modules\Pph21\Models\TaxBracketConfig>
     */
    public function resolveActiveBrackets(Carbon $referenceDate): Collection;
}