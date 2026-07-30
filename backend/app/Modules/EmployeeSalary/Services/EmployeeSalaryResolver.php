<?php

namespace App\Modules\EmployeeSalary\Services;

use App\Modules\Employee\Models\Employee;
use App\Modules\EmployeeSalary\Contracts\EmployeeSalaryResolverInterface;
use App\Modules\EmployeeSalary\DataTransferObjects\ResolvedSalaryLine;
use App\Modules\EmployeeSalary\Models\EmployeeSalary;
use App\Modules\EmployeeSalary\Models\EmployeeSalaryOverride;
use App\Modules\SalaryStructure\Contracts\SalaryStructureResolverInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EmployeeSalaryResolver implements EmployeeSalaryResolverInterface
{
    public function __construct(private SalaryStructureResolverInterface $structureResolver)
    {
    }

    public function resolveActiveVersion(Employee $employee, Carbon $referenceDate): ?EmployeeSalary
    {
        return EmployeeSalary::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->where('effective_date', '<=', $referenceDate->toDateString())
            ->orderByDesc('effective_date')
            ->first();
    }

    public function resolveComponents(Employee $employee, Carbon $referenceDate): array
    {
        $employeeSalary = $this->resolveActiveVersion($employee, $referenceDate);

        if (! $employeeSalary) {
            return [];
        }

        $structure = $this->structureResolver->resolveActiveVersion(
            $employee->company_id,
            $employeeSalary->salary_structure_code,
            $referenceDate,
        );

        return $this->buildLines($structure, $employeeSalary->overrides);
    }

    /**
     * Sama seperti resolveComponents(), tapi structure code & overrides-nya
     * dari draft (belum disimpan) — dipakai buat live preview di modal
     * Assign/Duplicate Salary sebelum user klik Simpan.
     *
     * @param  Collection<int, EmployeeSalaryOverride>  $draftOverrides
     */
    public function resolvePreview(
        Employee $employee,
        Carbon $referenceDate,
        string $salaryStructureCode,
        Collection $draftOverrides
    ): array {
        $structure = $this->structureResolver->resolveActiveVersion(
            $employee->company_id,
            $salaryStructureCode,
            $referenceDate,
        );

        return $this->buildLines($structure, $draftOverrides);
    }

    /**
     * @param  iterable<EmployeeSalaryOverride>  $overrides
     * @return array<int, ResolvedSalaryLine>
     */
    private function buildLines(mixed $structure, iterable $overrides): array
    {
        /** @var array<int, ResolvedSalaryLine> $lines keyed by salary_component_id */
        $lines = [];

        if ($structure) {
            foreach ($structure->details as $detail) {
                $lines[$detail->salary_component_id] = new ResolvedSalaryLine(
                    component: $detail->salaryComponent,
                    amount: $detail->effectiveAmount(),
                    percentageValue: $detail->effectivePercentageValue(),
                    percentageBase: $detail->effectivePercentageBase()?->value,
                    source: $detail->override_amount !== null || $detail->override_percentage_value !== null
                        ? 'structure'
                        : 'component_default',
                );
            }
        }

        foreach ($overrides as $override) {
            $component = $override->salaryComponent;

            $amount = $override->override_amount !== null
                ? (string) $override->override_amount
                : ($component->amount !== null ? (string) $component->amount : null);

            $percentageValue = $override->override_percentage_value !== null
                ? (string) $override->override_percentage_value
                : ($component->percentage_value !== null ? (string) $component->percentage_value : null);

            $percentageBase = $override->override_percentage_base?->value ?? $component->percentage_base?->value;

            $lines[$override->salary_component_id] = new ResolvedSalaryLine(
                component: $component,
                amount: $amount,
                percentageValue: $percentageValue,
                percentageBase: $percentageBase,
                source: 'employee_override',
            );
        }

        return array_values($lines);
    }
}