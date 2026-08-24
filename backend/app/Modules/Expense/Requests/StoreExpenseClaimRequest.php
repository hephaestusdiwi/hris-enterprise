<?php

namespace App\Modules\Expense\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // employee_id SENGAJA tidak ada di sini -- self-service,
            // employee selalu diturunkan dari authenticated user
            // (pola myReimbursements/myCashAdvances), bukan dari payload.
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'expense_subcategory_id' => ['nullable', 'exists:expense_subcategories,id'],
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:1000'],

            // Limit/format file sama persis dengan StoreReimbursementRequest
            // -- direuse, bukan dikarang baru.
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,pdf,csv,xlsx'],
        ];
    }

    public function messages(): array
    {
        return [
            'attachments.max' => 'Maksimal 5 file attachment per claim.',
            'attachments.*.max' => 'Ukuran tiap file maksimal 5MB.',
            'attachments.*.mimes' => 'Format file harus JPG, JPEG, PDF, CSV, atau XLSX.',
        ];
    }
}