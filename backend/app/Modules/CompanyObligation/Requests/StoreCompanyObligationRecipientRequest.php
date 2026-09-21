<?php

namespace App\Modules\CompanyObligation\Requests;

use App\Modules\CompanyObligation\Enums\CompanyObligationRecipientType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyObligationRecipientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient_type' => ['required', Rule::enum(CompanyObligationRecipientType::class)],
            'user_id' => [
                Rule::requiredIf(fn () => $this->input('recipient_type') === CompanyObligationRecipientType::User->value),
                'nullable',
                'exists:users,id',
            ],
            'role' => [
                Rule::requiredIf(fn () => $this->input('recipient_type') === CompanyObligationRecipientType::Role->value),
                'nullable',
                'string',
                'exists:roles,name',
            ],
        ];
    }
}