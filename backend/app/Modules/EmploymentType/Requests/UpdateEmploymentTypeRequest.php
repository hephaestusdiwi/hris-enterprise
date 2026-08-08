<?php

namespace App\Modules\EmploymentType\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmploymentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('employment_types', 'code')
                    ->ignore($this->route('employment_type')),
            ],
            'is_active' => ['boolean'],
        ];
    }
}