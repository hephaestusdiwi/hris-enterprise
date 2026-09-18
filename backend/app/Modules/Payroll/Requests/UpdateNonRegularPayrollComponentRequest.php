<?php

namespace App\Modules\Payroll\Requests;

use App\Modules\Payroll\Enums\NonRegularComponentCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNonRegularPayrollComponentRequest extends FormRequest
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
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('non_regular_payroll_components', 'code')
                    ->where('company_id', $this->input('company_id'))
                    ->ignore($this->route('nonRegularPayrollComponent')),
            ],
            'category' => ['required', Rule::enum(NonRegularComponentCategory::class)],
            'is_addition' => ['nullable', 'boolean'],
            'is_taxable' => ['boolean'],
            'include_in_bpjs_base' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}