<?php

namespace App\Modules\Pph21\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeTaxProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tax_id_number' => ['nullable', 'string', 'max:20'],
            'has_tax_id' => ['required', 'boolean'],
            'tax_method' => ['nullable', Rule::in(['gross', 'gross_up', 'netto'])],
        ];
    }
}