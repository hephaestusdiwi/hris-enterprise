<?php

namespace App\Modules\Bpjs\Contracts;

use App\Modules\Bpjs\Enums\BpjsProgram;
use App\Modules\Bpjs\Models\BpjsRateConfig;
use Carbon\Carbon;

interface BpjsRateResolverInterface
{
    public function resolveActiveVersion(int $companyId, BpjsProgram $program, Carbon $referenceDate): ?BpjsRateConfig;
}