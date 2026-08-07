<?php

namespace App\Modules\Bpjs\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyBpjsSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'default_health_cost_bearer' => ['required', Rule::in(['company_borne', 'employee_borne'])],
            'default_jht_cost_bearer' => ['required', Rule::in(['company_borne', 'employee_borne', 'not_participating'])],
        ];
    }
}