<?php

namespace App\Modules\Loan\Requests;

use App\Modules\Loan\Enums\LoanInterestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'principal' => ['required', 'numeric', 'min:1'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'interest_type' => ['nullable', Rule::in(array_column(LoanInterestType::cases(), 'value'))],
            'tenor' => ['required', 'integer', 'min:1', 'max:60'],
            'first_deduction_date' => ['required', 'date'],
            'purpose' => ['nullable', 'string'],
        ];
    }
}