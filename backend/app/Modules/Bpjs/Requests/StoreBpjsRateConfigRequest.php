<?php

namespace App\Modules\Bpjs\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBpjsRateConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'program' => ['required', Rule::in(['kesehatan', 'jht', 'jkk', 'jkm'])],
            'effective_date' => ['required', 'date'],
            // Jkk: rate diisi null di sini, tarifnya dari BpjsJkkRiskClassRate.
            'employee_rate_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'employer_rate_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'wage_base_cap' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}