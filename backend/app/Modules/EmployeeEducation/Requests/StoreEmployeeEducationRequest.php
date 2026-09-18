<?php

namespace App\Modules\EmployeeEducation\Requests;

use App\Modules\EmployeeEducation\Models\EmployeeEducation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeEducationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'education_level' => ['required', Rule::in(EmployeeEducation::LEVELS)],
            'institution_name' => ['required', 'string', 'max:255'],
            'major' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'graduation_status' => ['nullable', Rule::in(EmployeeEducation::GRADUATION_STATUSES)],
            'description' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ];
    }
}