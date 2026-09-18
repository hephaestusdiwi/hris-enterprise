<?php

namespace App\Modules\Payroll\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreThrPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'minimum_service_months' => ['required', 'integer', 'min:0', 'max:120'],
            'full_service_months' => ['required', 'integer', 'min:1', 'max:120', 'gte:minimum_service_months'],
            'include_in_bpjs_base' => ['boolean'],
            'is_active' => ['boolean'],
            'effective_date' => ['nullable', 'date'],
        ];
    }
}