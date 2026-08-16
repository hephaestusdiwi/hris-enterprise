<?php

namespace App\Modules\Reimbursement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StopReimbursementBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'max:500',
            ],
        ];
    }
}