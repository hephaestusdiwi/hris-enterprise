<?php

namespace App\Modules\Loan\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreviewLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'principal' => ['required', 'numeric', 'min:1'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tenor' => ['required', 'integer', 'min:1', 'max:60'],
        ];
    }
}