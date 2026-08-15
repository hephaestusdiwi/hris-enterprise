<?php

namespace App\Modules\NewJoiner\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendNewJoinerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'candidate_id' => ['required', 'integer', 'exists:candidates,id'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:30'],
        ];
    }
}