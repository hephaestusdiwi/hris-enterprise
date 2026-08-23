<?php

namespace App\Modules\Expense\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpensePolicyRequest extends FormRequest
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
            'description' => ['nullable', 'string'],
            'effective_date' => ['required', 'date'],
            'expiration_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
            'is_active' => ['boolean'],
            'category_ids' => ['sometimes', 'nullable', 'array'],
            'category_ids.*' => [
                'distinct',
                'integer',
                Rule::exists('expense_categories', 'id')
                    ->where('company_id', $this->input('company_id'))
                    ->where('deleted_at', null),
            ],
            'category_limits' => ['sometimes', 'nullable', 'array'],
            'category_limits.*.expense_category_id' => [
                'required',
                'integer',
                'distinct',
                Rule::in($this->input('category_ids', [])),
            ],
            'category_limits.*.limit_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}