<?php

namespace App\Modules\SalaryStructure\Services;

use App\Modules\SalaryStructure\Contracts\SalaryStructureResolverInterface;
use App\Modules\SalaryStructure\Models\SalaryStructure;
use Carbon\Carbon;

class SalaryStructureResolver implements SalaryStructureResolverInterface
{
    public function resolveActiveVersion(int $companyId, string $code, Carbon $referenceDate): ?SalaryStructure
    {
        return SalaryStructure::where('company_id', $companyId)
            ->where('code', $code)
            ->where('is_active', true)
            ->where('effective_date', '<=', $referenceDate->toDateString())
            ->orderByDesc('effective_date')
            ->first();
    }
}