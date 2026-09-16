<?php

namespace App\Modules\EmployeeDocument\Requests;

use App\Modules\EmployeeDocument\Models\EmployeeDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(EmployeeDocument::CATEGORIES)],
            'document' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'document.max' => 'Ukuran file maksimal 5MB.',
            'document.mimes' => 'Format file harus JPG, JPEG, PNG, atau PDF.',
        ];
    }
}