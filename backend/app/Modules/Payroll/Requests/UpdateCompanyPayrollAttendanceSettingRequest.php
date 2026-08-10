<?php

namespace App\Modules\Payroll\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyPayrollAttendanceSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'enable_attendance_integration' => ['required', 'boolean'],
            'overtime_hourly_divisor' => ['required', 'integer', 'min:1'],
            'overtime_multiplier_first_hour' => ['required', 'numeric', 'min:0'],
            'overtime_multiplier_next_hours' => ['required', 'numeric', 'min:0'],
            'overtime_salary_component_id' => ['nullable', 'exists:salary_components,id'],
            'late_deduction_per_minute' => ['nullable', 'numeric', 'min:0'],
            'late_deduction_salary_component_id' => ['nullable', 'exists:salary_components,id'],
        ];
    }
}