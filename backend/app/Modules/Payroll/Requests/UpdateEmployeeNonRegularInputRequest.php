<?php

namespace App\Modules\Payroll\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeNonRegularInputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0'],
            'is_addition' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string'],
        ];
    }
}
