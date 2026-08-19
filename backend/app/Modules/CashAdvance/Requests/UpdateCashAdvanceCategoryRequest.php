<?php

namespace App\Modules\CashAdvance\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCashAdvanceCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('cash_advance_categories', 'code')->ignore($this->route('cashAdvanceCategory'))],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}