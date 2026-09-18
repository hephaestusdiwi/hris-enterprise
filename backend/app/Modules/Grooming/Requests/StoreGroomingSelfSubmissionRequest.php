<?php

namespace App\Modules\Grooming\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGroomingSelfSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'string'], // base64 data URL, divalidasi lebih lanjut di Service
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.grooming_standard_item_id' => ['required', 'integer', 'exists:grooming_standard_items,id'],
            'answers.*.result' => ['required', 'string', 'in:pass,not_pass'],
            'answers.*.note' => ['nullable', 'string'],
        ];
    }
}