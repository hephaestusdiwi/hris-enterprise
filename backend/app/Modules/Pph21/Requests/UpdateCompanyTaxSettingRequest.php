<?php

namespace App\Modules\Pph21\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyTaxSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'default_tax_method' => ['required', Rule::in(['gross', 'gross_up', 'netto'])],
            'no_npwp_surcharge_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'position_cost_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'position_cost_monthly_cap' => ['required', 'numeric', 'min:0'],
            'position_cost_annual_cap' => ['required', 'numeric', 'min:0'],
        ];
    }
}