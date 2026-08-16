<?php

namespace App\Modules\Reimbursement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelReimbursementRequest extends FormRequest
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