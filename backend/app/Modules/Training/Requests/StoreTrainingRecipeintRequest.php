<?php

namespace App\Modules\Training\Requests;

use App\Modules\Training\Enums\TrainingRecipientType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrainingRecipientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient_type' => ['required', Rule::enum(TrainingRecipientType::class)],
            'user_id' => [
                Rule::requiredIf(fn () => $this->input('recipient_type') === TrainingRecipientType::User->value),
                'nullable',
                'exists:users,id',
            ],
            'role' => [
                Rule::requiredIf(fn () => $this->input('recipient_type') === TrainingRecipientType::Role->value),
                'nullable',
                'string',
                'exists:roles,name',
            ],
        ];
    }
}