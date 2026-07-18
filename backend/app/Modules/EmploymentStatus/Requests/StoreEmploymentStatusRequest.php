<?php

namespace App\Modules\EmploymentStatus\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmploymentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:employment_statuses,code'],
            'is_active' => ['boolean'],
        ];
    }
}