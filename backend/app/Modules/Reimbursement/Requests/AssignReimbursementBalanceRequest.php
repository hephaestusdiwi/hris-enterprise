<?php

namespace App\Modules\Reimbursement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignReimbursementBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'reimbursement_policy_id' => [
                'required',
                'exists:reimbursement_policies,id'
            ],
            'assigned_amount' => ['nullable', 'numeric', 'min:0'],
            'effective_date' => ['required', 'date'],
            'expiration_date' => [
                'nullable',
                'date',
                'after_or_equal:effective_date'
            ],
        ];
    }
}