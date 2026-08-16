<?php

namespace App\Modules\Reimbursement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReimbursementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reimbursement_balance_id' => [
                'required',
                'exists:reimbursement_balances,id',
            ],

            'transaction_date' => [
                'required',
                'date',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.reimbursement_benefit_id' => [
                'required',
                'exists:reimbursement_benefits,id',
            ],

            'items.*.amount' => [
                'required',
                'numeric',
                'min:1',
            ],

            'items.*.notes' => [
                'nullable',
                'string',
                'max:500',
            ],

            'attachments' => [
                'nullable',
                'array',
                'max:5',
            ],

            'attachments.*' => [
                'file',
                'max:5120',
                'mimes:jpg,jpeg,pdf,csv,xlsx',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' =>
                'Minimal satu benefit/item harus diisi.',

            'attachments.max' =>
                'Maksimal 5 file attachment per request.',

            'attachments.*.max' =>
                'Ukuran tiap file maksimal 5MB.',

            'attachments.*.mimes' =>
                'Format file harus JPG, JPEG, PDF, CSV, atau XLSX.',
        ];
    }
}