<?php

namespace App\Modules\Expense\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DecideExpenseClaimApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'in:approve,reject'],
            'notes' => ['nullable', 'string'],
        ];
    }
}