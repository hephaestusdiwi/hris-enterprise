<?php

namespace App\Modules\Pph21\Services;

use App\Modules\Pph21\Contracts\TerRateResolverInterface;
use App\Modules\Pph21\Enums\TerCategory;
use App\Modules\Pph21\Models\TerRateBracket;
use Carbon\Carbon;

class TerRateResolver implements TerRateResolverInterface
{
    public function resolveBracket(TerCategory $category, string $grossIncome, Carbon $referenceDate): ?TerRateBracket
    {
        // Langkah 1: cari "versi" TER aktif (effective_date terbaru yang <= referenceDate),
        // mirror resolveActiveVersion di modul lain.
        $activeEffectiveDate = TerRateBracket::where('category', $category->value)
            ->where('is_active', true)
            ->where('effective_date', '<=', $referenceDate->toDateString())
            ->orderByDesc('effective_date')
            ->value('effective_date');

        if (! $activeEffectiveDate) {
            return null;
        }

        // Langkah 2: dalam versi itu, cari baris yang income_from <= grossIncome < income_to (atau income_to null).
        return TerRateBracket::where('category', $category->value)
            ->where('effective_date', $activeEffectiveDate)
            ->where('income_from', '<=', $grossIncome)
            ->where(function ($query) use ($grossIncome) {
                $query->whereNull('income_to')->orWhere('income_to', '>', $grossIncome);
            })
            ->first();
    }
}