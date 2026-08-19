<?php

namespace App\Modules\CashAdvance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCashAdvanceSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.cash_advance_category_id' => ['required', 'exists:cash_advance_categories,id'],
            'items.*.cash_advance_request_item_id' => ['nullable', 'integer', 'exists:cash_advance_request_items,id'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.actual_amount' => ['required', 'numeric', 'min:0'],
            'items.*.returned_amount' => ['nullable', 'numeric', 'min:0'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,pdf,csv,xlsx'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Minimal satu item settlement harus diisi.',
            'attachments.max' => 'Maksimal 5 file attachment per settlement.',
            'attachments.*.max' => 'Ukuran tiap file maksimal 5MB.',
            'attachments.*.mimes' => 'Format file harus JPG, JPEG, PDF, CSV, atau XLSX.',
        ];
    }
}