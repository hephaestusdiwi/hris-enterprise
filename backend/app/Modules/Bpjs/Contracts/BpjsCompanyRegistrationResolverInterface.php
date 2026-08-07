<?php

namespace App\Modules\Bpjs\Contracts;

use App\Modules\Bpjs\Models\BpjsCompanyRegistration;
use Carbon\Carbon;

interface BpjsCompanyRegistrationResolverInterface
{
    public function resolveActiveVersion(int $companyId, string $nppNumber, Carbon $referenceDate): ?BpjsCompanyRegistration;
}