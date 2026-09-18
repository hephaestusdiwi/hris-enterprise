<?php

namespace App\Modules\EmployeeExperience\Requests;

use App\Modules\EmployeeExperience\Models\EmployeeExperience;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeExperienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'position_title' => ['required', 'string', 'max:255'],
            'employment_type' => ['nullable', Rule::in(EmployeeExperience::EMPLOYMENT_TYPES)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'reason_for_leaving' => ['nullable', 'string', 'max:255'],
        ];
    }
}