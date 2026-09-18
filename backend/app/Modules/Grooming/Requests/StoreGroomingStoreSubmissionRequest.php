<?php

namespace App\Modules\Grooming\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGroomingStoreSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.grooming_standard_item_id' => ['required', 'integer', 'exists:grooming_standard_items,id'],
            'answers.*.result' => ['required', 'string', 'in:pass,not_pass'],
            'answers.*.note' => ['nullable', 'string'],
        ];
    }
}