<?php

namespace App\Modules\Expense\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpensePolicyRequest extends FormRequest
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
            'category_ids' => ['nullable', 'array'],
            // Rule::exists di-scope ke company_id yang sama dengan policy
            // (bukan cuma exists:expense_categories,id polos) supaya
            // category dari company lain tidak bisa di-attach. Soft-deleted
            // category juga tidak dianggap valid -- where('deleted_at', null)
            // otomatis di-translate jadi whereNull() oleh query builder.
            // `distinct` menolak duplicate ID di array yang sama.
            'category_ids.*' => [
                'distinct',
                'integer',
                Rule::exists('expense_categories', 'id')
                    ->where('company_id', $this->input('company_id'))
                    ->where('deleted_at', null),
            ],
            // OPSIONAL, backward-compatible: category_ids TETAP bisa
            // dikirim sendirian tanpa category_limits (semua limit_amount
            // jadi null/unlimited). category_limits cuma override limit
            // untuk category yang memang disebut di sini.
            'category_limits' => ['nullable', 'array'],
            'category_limits.*.expense_category_id' => [
                'required',
                'integer',
                'distinct',
                // Harus salah satu dari category_ids yang dikirim di
                // request yang SAMA -- tidak bisa kasih limit untuk
                // category yang tidak ikut di-attach.
                Rule::in($this->input('category_ids', [])),
            ],
            'category_limits.*.limit_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}