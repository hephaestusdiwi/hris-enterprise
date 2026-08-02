<?php

namespace App\Modules\EmployeeAllowance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoidEmployeeAllowanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string'],
        ];
    }
}