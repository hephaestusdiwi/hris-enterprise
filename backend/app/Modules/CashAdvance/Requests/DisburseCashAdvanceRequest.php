<?php

namespace App\Modules\CashAdvance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DisburseCashAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'disbursement_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}