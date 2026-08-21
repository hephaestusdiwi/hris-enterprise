<?php

namespace App\Modules\Expense\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseSubcategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Company subcategory selalu diturunkan dari kategori induknya
            // (lihat ExpenseSubcategory::category()) -- cukup exists check,
            // sama seperti parent_position_id di StorePositionRequest.
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('expense_subcategories', 'code')->where('expense_category_id', $this->input('expense_category_id')),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}