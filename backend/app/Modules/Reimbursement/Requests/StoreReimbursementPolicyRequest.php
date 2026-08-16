<?php

namespace App\Modules\Reimbursement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReimbursementPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'effective_date' => [
                'required',
                'date',
            ],

            'expiration_date' => [
                'nullable',
                'date',
                'after_or_equal:effective_date',
            ],

            'default_limit_amount' => [
                'nullable',
                'numeric',
                'min:0',
            ],
        ];
    }
}