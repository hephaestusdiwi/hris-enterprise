<?php

namespace App\Modules\Loan\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DecideLoanApprovalRequest extends FormRequest
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