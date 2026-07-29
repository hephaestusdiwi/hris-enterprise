<?php

namespace App\Modules\SalaryStructure\Contracts;

use App\Modules\SalaryStructure\Models\SalaryStructure;
use Carbon\Carbon;

interface SalaryStructureResolverInterface
{
    public function resolveActiveVersion(int $companyId, string $code, Carbon $referenceDate): ?SalaryStructure;
}