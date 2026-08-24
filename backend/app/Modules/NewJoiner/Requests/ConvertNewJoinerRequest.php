<?php

namespace App\Modules\NewJoiner\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConvertNewJoinerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'job_level_id' => ['nullable', 'integer', 'exists:job_levels,id'],
            'working_schedule_id' => ['nullable', 'integer', 'exists:working_schedules,id'],
            'employment_status_id' => ['nullable', 'integer', 'exists:employment_statuses,id'],
            'manager_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ];
    }
}