<?php

namespace App\Modules\Bpjs\DataTransferObjects;

use App\Modules\Bpjs\Enums\BpjsCostBearer;
use App\Modules\Bpjs\Enums\BpjsProgram;

final class ResolvedBpjsContribution
{
    public function __construct(
        public readonly BpjsProgram $program,
        public readonly string $wageBaseUsed,
        public readonly string $employeeAmount,
        public readonly string $employerAmount,
        public readonly BpjsCostBearer $costBearerApplied,
        public readonly ?int $rateSourceId, // id baris BpjsRateConfig, atau BpjsJkkRiskClassRate khusus utk Jkk
    ) {
    }
}