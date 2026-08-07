<?php

namespace App\Modules\JobVacancy\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobVacancyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hiring_requisition_id' => ['required', 'integer', 'exists:hiring_requisitions,id'],
            'hiring_manager_employee_id' => ['required', 'integer', 'exists:employees,id'],
            'recruiter_employee_id' => ['required', 'integer', 'exists:employees,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'requirements' => ['nullable', 'string'],
            'employment_type_id' => ['nullable', 'integer', 'exists:employment_types,id'],
            'visibility' => ['required', 'string', 'in:internal,external,both'],
            'application_deadline' => ['nullable', 'date'],
            'application_method' => ['required', 'string', 'in:internal,external'],
            'external_apply_url' => ['nullable', 'url', 'required_if:application_method,external'],
        ];
    }
}