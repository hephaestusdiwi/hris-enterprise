<?php

namespace App\Modules\SalaryStructure\Requests;

use App\Modules\SalaryComponent\Enums\PercentageBase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalaryStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'effective_date' => [
                'required',
                'date',
                Rule::unique('salary_structures', 'effective_date')
                    ->where('company_id', $this->input('company_id'))
                    ->where('code', $this->input('code')),
            ],
            'is_active' => ['boolean'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.salary_component_id' => ['required', 'exists:salary_components,id'],
            'details.*.override_amount' => ['nullable', 'numeric', 'min:0'],
            'details.*.override_percentage_value' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'details.*.override_percentage_base' => ['nullable', Rule::enum(PercentageBase::class)],
            'details.*.display_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}