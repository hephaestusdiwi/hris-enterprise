<?php

namespace App\Modules\Candidate\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCandidateApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // publik, tidak perlu login
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'source' => ['required', 'string', 'in:career_site,linkedin,jobstreet,referral,import,other'],
            'cv' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ];
    }
}