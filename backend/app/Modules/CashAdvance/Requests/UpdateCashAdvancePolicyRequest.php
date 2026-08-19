<?php

namespace App\Modules\CashAdvance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCashAdvancePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'effective_date' => ['sometimes', 'date'],
            'settlement_due_days' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'category_ids' => ['sometimes', 'nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:cash_advance_categories,id'],
        ];
    }
}