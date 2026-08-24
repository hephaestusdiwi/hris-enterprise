<?php

namespace App\Modules\Expense\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelExpenseClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}