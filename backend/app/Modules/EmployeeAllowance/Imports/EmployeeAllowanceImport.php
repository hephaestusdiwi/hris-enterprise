<?php

namespace App\Modules\EmployeeAllowance\Imports;

use App\Modules\Employee\Models\Employee;
use App\Modules\EmployeeAllowance\Enums\EmployeeAllowanceStatus;
use App\Modules\EmployeeAllowance\Models\EmployeeAllowance;
use App\Modules\SalaryComponent\Models\SalaryComponent;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class EmployeeAllowanceImport implements ToCollection, WithHeadingRow
{
    public array $created = [];
    public array $errors = [];

    public function __construct(private ?int $actorUserId)
    {
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +1 heading row, +1 karena index dari 0

            $employee = Employee::where('employee_number', trim((string) $row['employee_number']))->first();
            $component = SalaryComponent::where('code', trim((string) $row['salary_component_code']))->first();

            if (! $employee) {
                $this->errors[] = "Baris {$rowNumber}: employee_number '{$row['employee_number']}' tidak ditemukan.";

                continue;
            }

            if (! $component) {
                $this->errors[] = "Baris {$rowNumber}: salary_component_code '{$row['salary_component_code']}' tidak ditemukan.";

                continue;
            }

            if ($component->company_id !== $employee->company_id || $component->category->value !== 'allowance' || ! $component->is_addition) {
                $this->errors[] = "Baris {$rowNumber}: salary_component_code '{$row['salary_component_code']}' bukan Allowance yang valid untuk employee ini.";

                continue;
            }

            if (empty($row['payroll_period_year']) || empty($row['payroll_period_month']) || empty($row['amount'])) {
                $this->errors[] = "Baris {$rowNumber}: payroll_period_year, payroll_period_month, dan amount wajib diisi.";

                continue;
            }

            $allowance = EmployeeAllowance::create([
                'employee_id' => $employee->id,
                'salary_component_id' => $component->id,
                'payroll_period_year' => (int) $row['payroll_period_year'],
                'payroll_period_month' => (int) $row['payroll_period_month'],
                'amount' => (float) $row['amount'],
                'remark' => $row['remark'] ?? null,
                'status' => EmployeeAllowanceStatus::Draft->value,
                'created_by_user_id' => $this->actorUserId,
            ]);

            $this->created[] = $allowance->id;
        }
    }
}