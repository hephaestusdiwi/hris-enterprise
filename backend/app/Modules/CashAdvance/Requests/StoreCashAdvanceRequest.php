<?php

namespace App\Modules\CashAdvance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCashAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cash_advance_policy_id' => ['required', 'exists:cash_advance_policies,id'],
            'purpose' => ['required', 'string', 'max:200'],
            'date_of_use' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.cash_advance_category_id' => ['required', 'exists:cash_advance_categories,id'],
            'items.*.name' => ['required', 'string', 'max:150'],
            'items.*.description' => ['nullable', 'string', 'max:500'],
            'items.*.amount' => ['required', 'numeric', 'min:1'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,pdf,csv,xlsx'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Minimal satu detail Cash Advance harus diisi.',
            'attachments.max' => 'Maksimal 5 file attachment per request.',
            'attachments.*.max' => 'Ukuran tiap file maksimal 5MB.',
            'attachments.*.mimes' => 'Format file harus JPG, JPEG, PDF, CSV, atau XLSX.',
        ];
    }
}