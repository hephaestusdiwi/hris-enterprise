<?php

namespace App\Modules\Pph21\Services;

use App\Modules\Pph21\Contracts\TaxBracketResolverInterface;
use App\Modules\Pph21\Models\TaxBracketConfig;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TaxBracketResolver implements TaxBracketResolverInterface
{
    public function resolveActiveBrackets(Carbon $referenceDate): Collection
    {
        $activeEffectiveDate = TaxBracketConfig::where('is_active', true)
            ->where('effective_date', '<=', $referenceDate->toDateString())
            ->orderByDesc('effective_date')
            ->value('effective_date');

        if (! $activeEffectiveDate) {
            return collect();
        }

        return TaxBracketConfig::where('effective_date', $activeEffectiveDate)
            ->orderBy('income_from')
            ->get();
    }
}