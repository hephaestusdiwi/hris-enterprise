<?php

namespace App\Modules\EmployeeSalary\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreviewEmployeeSalaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'salary_structure_code' => ['required', 'string'],
            'effective_date' => ['required', 'date'],
            'overrides' => ['sometimes', 'array'],
            'overrides.*.salary_component_id' => ['required', 'integer', 'exists:salary_components,id'],
            'overrides.*.override_amount' => ['nullable', 'numeric', 'min:0'],
            'overrides.*.override_percentage_value' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'overrides.*.override_percentage_base' => ['nullable', 'in:basic_salary,gross_salary'],
        ];
    }
}