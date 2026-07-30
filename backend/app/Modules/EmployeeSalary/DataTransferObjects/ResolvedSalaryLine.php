<?php

namespace App\Modules\EmployeeSalary\DataTransferObjects;

use App\Modules\SalaryComponent\Models\SalaryComponent;

final class ResolvedSalaryLine
{
    public function __construct(
        public readonly SalaryComponent $component,
        public readonly ?string $amount,
        public readonly ?string $percentageValue,
        public readonly ?string $percentageBase,
        public readonly string $source, // 'employee_override' | 'structure' | 'component_default'
    ) {
    }
}