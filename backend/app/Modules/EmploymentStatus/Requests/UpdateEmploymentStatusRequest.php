<?php

namespace App\Modules\EmploymentStatus\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmploymentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('employment_statuses', 'code')->ignore($this->route('employment_status'))],
            'is_active' => ['boolean'],
        ];
    }
}