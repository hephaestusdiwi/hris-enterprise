<?php

namespace App\Modules\Bpjs\Contracts;

use App\Modules\Bpjs\Models\BpjsJkkRiskClassRate;
use Carbon\Carbon;

interface BpjsJkkRiskClassResolverInterface
{
    public function resolveActiveVersion(int $riskClass, Carbon $referenceDate): ?BpjsJkkRiskClassRate;
}