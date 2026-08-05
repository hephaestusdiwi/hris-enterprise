<?php

namespace App\Modules\EmploymentType\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmploymentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required',  'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:employment_types,code',
            ],
            'is_active' => ['boolean'],
        ];
    }
}