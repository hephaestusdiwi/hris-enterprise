<?php

namespace App\Modules\EmployeeDeduction\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoidEmployeeDeductionRequest extends FormRequest
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