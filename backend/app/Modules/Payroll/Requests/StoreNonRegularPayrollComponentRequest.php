<?php

namespace App\Modules\Payroll\Requests;

use App\Modules\Payroll\Enums\NonRegularComponentCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNonRegularPayrollComponentRequest extends FormRequest
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
                Rule::unique('non_regular_payroll_components', 'code')->where('company_id', $this->input('company_id')),
            ],
            'category' => ['required', Rule::enum(NonRegularComponentCategory::class)],
            'is_addition' => ['nullable', 'boolean'],
            'is_taxable' => ['boolean'],
            'include_in_bpjs_base' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $category = NonRegularComponentCategory::tryFrom($this->input('category'));

            if ($category && $category !== NonRegularComponentCategory::Adjustment && $this->filled('is_addition')) {
                $expected = $category->defaultIsAddition();

                if ($this->boolean('is_addition') !== $expected) {
                    $validator->errors()->add(
                        'is_addition',
                        'Kategori '.$category->label().' harus '.($expected ? 'penambah (earning)' : 'pengurang (deduction)').'.'
                    );
                }
            }
        });
    }
}
