<?php

namespace App\Modules\Bpjs\Services;

use App\Modules\Bpjs\Contracts\BpjsCompanyRegistrationResolverInterface;
use App\Modules\Bpjs\Models\BpjsCompanyRegistration;
use Carbon\Carbon;

class BpjsCompanyRegistrationResolver implements BpjsCompanyRegistrationResolverInterface
{
    public function resolveActiveVersion(int $companyId, string $nppNumber, Carbon $referenceDate): ?BpjsCompanyRegistration
    {
        return BpjsCompanyRegistration::where('company_id', $companyId)
            ->where('npp_number', $nppNumber)
            ->where('is_active', true)
            ->where('effective_date', '<=', $referenceDate->toDateString())
            ->orderByDesc('effective_date')
            ->first();
    }
}