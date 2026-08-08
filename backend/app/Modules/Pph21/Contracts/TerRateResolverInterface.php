<?php

namespace App\Modules\Pph21\Contracts;

use App\Modules\Pph21\Enums\TerCategory;
use App\Modules\Pph21\Models\TerRateBracket;
use Carbon\Carbon;

interface TerRateResolverInterface
{
    /**
     * Cari baris bracket yang income_from <= grossIncome < income_to (atau income_to null)
     * dari versi TER yang berlaku pada referenceDate.
     */
    public function resolveBracket(TerCategory $category, string $grossIncome, Carbon $referenceDate): ?TerRateBracket;
}