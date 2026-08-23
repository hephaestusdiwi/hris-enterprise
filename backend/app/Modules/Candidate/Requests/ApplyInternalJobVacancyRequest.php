<?php

namespace App\Modules\Candidate\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyInternalJobVacancyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // auth:sanctum sudah gate di level route
    }

    public function rules(): array
    {
        return [
            'cv' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ];
    }
}