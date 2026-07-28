<?php

namespace App\Modules\SalaryComponent\Requests;

use App\Modules\SalaryComponent\Enums\CalculationMethod;
use App\Modules\SalaryComponent\Enums\PercentageBase;
use App\Modules\SalaryComponent\Enums\SalaryComponentCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalaryComponentRequest extends FormRequest
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
                Rule::unique('salary_components', 'code')
                    ->where('company_id', $this->input('company_id'))
                    ->ignore($this->route('salaryComponent')),
            ],
            'category' => ['required', Rule::enum(SalaryComponentCategory::class)],
            'is_addition' => ['boolean'],
            'calculation_method' => ['required', Rule::enum(CalculationMethod::class)],
            'amount' => [
                Rule::requiredIf($this->input('calculation_method') === CalculationMethod::Fixed->value),
                'nullable', 'numeric', 'min:0',
            ],
            'percentage_value' => [
                Rule::requiredIf($this->input('calculation_method') === CalculationMethod::Percentage->value),
                'nullable', 'numeric', 'min:0', 'max:100',
            ],
            'percentage_base' => [
                Rule::requiredIf($this->input('calculation_method') === CalculationMethod::Percentage->value),
                'nullable', Rule::enum(PercentageBase::class),
            ],
            'is_taxable' => ['boolean'],
            'include_in_bpjs_base' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}