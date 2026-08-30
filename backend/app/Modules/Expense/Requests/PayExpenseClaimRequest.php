<?php

namespace App\Modules\Expense\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayExpenseClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}