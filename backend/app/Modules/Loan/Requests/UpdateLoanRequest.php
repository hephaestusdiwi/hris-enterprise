<?php

namespace App\Modules\Loan\Requests;

use App\Modules\Loan\Enums\LoanInterestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'principal' => ['sometimes', 'numeric', 'min:1'],
            'interest_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'interest_type' => ['sometimes', 'nullable', Rule::in(array_column(LoanInterestType::cases(), 'value'))],
            'tenor' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'first_deduction_date' => ['sometimes', 'date'],
            'purpose' => ['sometimes', 'nullable', 'string'],
        ];
    }
}