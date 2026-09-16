<?php

namespace App\Modules\Candidate\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCandidateBlacklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'full_name' => ['nullable', 'string'],
            'candidate_id' => ['nullable', 'integer', 'exists:candidates,id'],
            'reason' => ['required', 'string'],
        ];
    }
}