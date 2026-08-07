<?php

namespace App\Modules\Bpjs\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBpjsJkkRiskClassRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'risk_class' => ['required', 'integer', 'between:1,5'],
            'effective_date' => ['required', 'date'],
            'employer_rate_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}