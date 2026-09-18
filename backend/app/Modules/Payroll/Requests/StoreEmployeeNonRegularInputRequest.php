<?php

namespace App\Modules\Payroll\Requests;

use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Enums\EmployeeNonRegularInputStatus;
use App\Modules\Payroll\Models\NonRegularPayrollComponent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeNonRegularInputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'non_regular_payroll_component_id' => ['required', 'exists:non_regular_payroll_components,id'],
            'payroll_period_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'payroll_period_month' => ['required', 'integer', 'between:1,12'],
            'amount' => ['required', 'numeric', 'min:0'],
            'is_addition' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in([EmployeeNonRegularInputStatus::Draft->value, EmployeeNonRegularInputStatus::Ready->value])],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $employee = Employee::find($this->input('employee_id'));
            $component = NonRegularPayrollComponent::find($this->input('non_regular_payroll_component_id'));

            if (! $employee || ! $component) {
                return;
            }

            if ($component->company_id !== $employee->company_id) {
                $validator->errors()->add('non_regular_payroll_component_id', 'Component harus berada di company yang sama dengan employee.');
            }

            if (! $component->is_active) {
                $validator->errors()->add('non_regular_payroll_component_id', 'Component ini sudah nonaktif.');
            }

            $resolvedIsAddition = $this->filled('is_addition') ? $this->boolean('is_addition') : $component->resolvedIsAddition();

            if ($resolvedIsAddition === null) {
                $validator->errors()->add('is_addition', 'Component kategori Adjustment wajib menentukan is_addition (earning/deduction) secara eksplisit.');
            }
        });
    }

    /**
     * is_addition final yang dipakai service — hasil override eksplisit atau
     * fallback ke default kategori component. Dipanggil controller SETELAH
     * validasi lolos, supaya default kategori Adjustment tidak pernah null.
     */
    public function resolvedIsAddition(): bool
    {
        if ($this->filled('is_addition')) {
            return $this->boolean('is_addition');
        }

        $component = NonRegularPayrollComponent::find($this->input('non_regular_payroll_component_id'));

        return (bool) $component?->resolvedIsAddition();
    }
}