<?php

namespace App\Modules\Pph21\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaxBracketConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'effective_date' => ['required', 'date'],
            'income_from' => ['required', 'numeric', 'min:0'],
            'income_to' => ['nullable', 'numeric', 'gt:income_from'],
            'rate_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}