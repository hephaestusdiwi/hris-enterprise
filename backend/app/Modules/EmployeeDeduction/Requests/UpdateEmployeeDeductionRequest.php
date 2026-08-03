<?php

namespace App\Modules\EmployeeDeduction\Requests;

use App\Modules\Employee\Models\Employee;
use App\Modules\EmployeeDeduction\Enums\EmployeeDeductionStatus;
use App\Modules\SalaryComponent\Models\SalaryComponent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeDeductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'salary_component_id' => ['required', 'exists:salary_components,id'],
            'payroll_period_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'payroll_period_month' => ['required', 'integer', 'between:1,12'],
            'amount' => ['required', 'numeric', 'min:0'],
            'remark' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in([EmployeeDeductionStatus::Draft->value, EmployeeDeductionStatus::Ready->value])],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $employee = Employee::find($this->input('employee_id'));
            $component = SalaryComponent::find($this->input('salary_component_id'));

            if (! $employee || ! $component) {
                return;
            }

            if ($component->company_id !== $employee->company_id) {
                $validator->errors()->add('salary_component_id', 'Salary Component harus berada di company yang sama dengan employee.');
            }

            if ($component->category->value !== 'deduction' || $component->is_addition) {
                $validator->errors()->add('salary_component_id', 'Hanya Salary Component berkategori Deduction yang boleh dipilih.');
            }
        });
    }
}