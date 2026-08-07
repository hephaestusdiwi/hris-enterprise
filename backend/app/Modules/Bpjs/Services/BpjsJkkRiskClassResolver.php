<?php

namespace App\Modules\Bpjs\Services;

use App\Modules\Bpjs\Contracts\BpjsJkkRiskClassResolverInterface;
use App\Modules\Bpjs\Models\BpjsJkkRiskClassRate;
use Carbon\Carbon;

class BpjsJkkRiskClassResolver implements BpjsJkkRiskClassResolverInterface
{
    public function resolveActiveVersion(int $riskClass, Carbon $referenceDate): ?BpjsJkkRiskClassRate
    {
        return BpjsJkkRiskClassRate::where('risk_class', $riskClass)
            ->where('is_active', true)
            ->where('effective_date', '<=', $referenceDate->toDateString())
            ->orderByDesc('effective_date')
            ->first();
    }
}