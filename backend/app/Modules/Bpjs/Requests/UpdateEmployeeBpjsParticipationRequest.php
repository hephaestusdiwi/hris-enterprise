<?php

namespace App\Modules\Bpjs\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeBpjsParticipationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bpjs_health_number' => ['nullable', 'string', 'max:20'],
            'bpjs_health_family_count' => ['nullable', 'integer', 'min:0', 'max:20'],
            'bpjs_health_start_date' => ['nullable', 'date'],
            'bpjs_health_cost_bearer' => ['required', Rule::in(['default', 'company_borne', 'employee_borne'])],

            'bpjs_employment_number' => ['nullable', 'string', 'max:20'],
            'bpjs_registration_npp_number' => ['nullable', 'string', 'max:50'],
            'bpjs_employment_start_date' => ['nullable', 'date'],
            'jht_cost_bearer' => ['required', Rule::in(['default', 'company_borne', 'employee_borne', 'not_participating'])],
        ];
    }
}