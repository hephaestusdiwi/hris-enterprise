<?php

namespace App\Modules\Bpjs\Services;

use App\Modules\Bpjs\Contracts\BpjsRateResolverInterface;
use App\Modules\Bpjs\Enums\BpjsProgram;
use App\Modules\Bpjs\Models\BpjsRateConfig;
use Carbon\Carbon;

class BpjsRateResolver implements BpjsRateResolverInterface
{
    public function resolveActiveVersion(int $companyId, BpjsProgram $program, Carbon $referenceDate): ?BpjsRateConfig
    {
        return BpjsRateConfig::where('company_id', $companyId)
            ->where('program', $program->value)
            ->where('is_active', true)
            ->where('effective_date', '<=', $referenceDate->toDateString())
            ->orderByDesc('effective_date')
            ->first();
    }
}