<?php

namespace App\Modules\Loan\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string'],
        ];
    }
}