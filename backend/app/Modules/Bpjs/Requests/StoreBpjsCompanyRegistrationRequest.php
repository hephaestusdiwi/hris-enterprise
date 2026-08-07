<?php

namespace App\Modules\Bpjs\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBpjsCompanyRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'npp_number' => ['required', 'string', 'max:50'],
            'risk_class' => ['required', 'integer', 'between:1,5'],
            'label' => ['nullable', 'string', 'max:255'],
            'effective_date' => ['required', 'date'],
        ];
    }
}