<?php

namespace App\Modules\Expense\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseClaimAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Key `attachments` (array, min 1 max 5) -- BUKAN `file`
            // singular. Ini contract yang sudah diperbaiki di
            // CashAdvanceAttachmentController sebelumnya, dipakai dari
            // awal di sini supaya tidak mengulang bug yang sama.
            'attachments' => ['required', 'array', 'min:1', 'max:5'],
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