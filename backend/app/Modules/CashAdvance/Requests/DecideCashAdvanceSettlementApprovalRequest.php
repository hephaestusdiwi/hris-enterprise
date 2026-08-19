<?php

namespace App\Modules\CashAdvance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DecideCashAdvanceSettlementApprovalRequest extends FormRequest
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