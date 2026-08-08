<?php

namespace App\Modules\Pph21\Services;

use App\Modules\Pph21\Contracts\EmployeePtkpStatusResolverInterface;
use App\Modules\Pph21\Models\EmployeePtkpStatus;

class EmployeePtkpStatusResolver implements EmployeePtkpStatusResolverInterface
{
    public function resolveForTaxYear(int $employeeId, int $taxYear): ?EmployeePtkpStatus
    {
        return EmployeePtkpStatus::where('employee_id', $employeeId)
            ->where('tax_year', '<=', $taxYear)
            ->orderByDesc('tax_year')
            ->first();
    }
}