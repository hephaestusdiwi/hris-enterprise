<?php

namespace App\Modules\Grooming\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGroomingStandardVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'effective_date' => ['required', 'date'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.name' => ['required_with:items', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.mandatory' => ['nullable', 'boolean'],
            'items.*.requires_note_on_fail' => ['nullable', 'boolean'],
            'items.*.requires_photo' => ['nullable', 'boolean'],
        ];
    }
}