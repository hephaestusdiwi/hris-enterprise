<?php

namespace App\Modules\EmployeeSalary\Requests;

use App\Modules\SalaryComponent\Enums\PercentageBase;
use App\Modules\SalaryStructure\Models\SalaryStructure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeSalaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'salary_structure_code' => ['required', 'string', 'max:50'],
            'effective_date' => [
                'required',
                'date',
                Rule::unique('employee_salaries', 'effective_date')->where('employee_id', $this->input('employee_id')),
            ],
            'is_active' => ['boolean'],
            'overrides' => ['nullable', 'array'],
            'overrides.*.salary_component_id' => ['required', 'exists:salary_components,id'],
            'overrides.*.override_amount' => ['nullable', 'numeric', 'min:0'],
            'overrides.*.override_percentage_value' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'overrides.*.override_percentage_base' => ['nullable', Rule::enum(PercentageBase::class)],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $employeeId = $this->input('employee_id');
            $code = $this->input('salary_structure_code');

            if (! $employeeId || ! $code) {
                return;
            }

            $employee = \App\Modules\Employee\Models\Employee::find($employeeId);

            if (! $employee) {
                return;
            }

            $exists = SalaryStructure::where('company_id', $employee->company_id)
                ->where('code', $code)
                ->exists();

            if (! $exists) {
                $validator->errors()->add('salary_structure_code', 'Salary Structure dengan kode ini tidak ditemukan di company employee.');
            }
        });
    }
}