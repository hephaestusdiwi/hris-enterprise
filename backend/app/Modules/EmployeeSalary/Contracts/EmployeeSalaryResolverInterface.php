<?php

namespace App\Modules\EmployeeSalary\Contracts;

use App\Modules\Employee\Models\Employee;
use App\Modules\EmployeeSalary\Models\EmployeeSalary;
use Carbon\Carbon;
use Illuminate\Support\Collection;

interface EmployeeSalaryResolverInterface
{
    public function resolveActiveVersion(Employee $employee, Carbon $referenceDate): ?EmployeeSalary;

    /**
     * @return array<int, \App\Modules\EmployeeSalary\DataTransferObjects\ResolvedSalaryLine>
     */
    public function resolveComponents(Employee $employee, Carbon $referenceDate): array;

    public function resolvePreview(
        Employee $employee,
        Carbon $referenceDate,
        string $salaryStructureCode,
        Collection $draftOverrides
    ): array;
}