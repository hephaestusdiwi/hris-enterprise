<?php

namespace App\Modules\Pph21\Services;

use App\Modules\Pph21\Contracts\PtkpResolverInterface;
use App\Modules\Pph21\Enums\PtkpStatus;
use App\Modules\Pph21\Models\PtkpConfig;
use Carbon\Carbon;

class PtkpResolver implements PtkpResolverInterface
{
    public function resolveActiveVersion(PtkpStatus $status, Carbon $referenceDate): ?PtkpConfig
    {
        return PtkpConfig::where('ptkp_status', $status->value)
            ->where('is_active', true)
            ->where('effective_date', '<=', $referenceDate->toDateString())
            ->orderByDesc('effective_date')
            ->first();
    }
}