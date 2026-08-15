<?php

namespace App\Modules\Offering\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RespondOfferingRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'response' => ['required', 'string', 'in:accepted,declined'],
            'notes' => ['nullable', 'string'],
        ];
    }
}