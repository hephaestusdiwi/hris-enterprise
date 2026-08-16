<?php

namespace App\Modules\Reimbursement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DecideReimbursementApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => [
                'required',
                'in:approve,reject',
            ],

            'notes' => [
                'nullable',
                'string',
            ],
        ];
    }
}