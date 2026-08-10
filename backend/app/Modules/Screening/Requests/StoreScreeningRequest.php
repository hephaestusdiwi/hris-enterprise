<?php

namespace App\Modules\Screening\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScreeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'candidate_id' => ['required', 'integer', 'exists:candidates,id'],
            'reviewer_employee_id' => ['required', 'integer', 'exists:employees,id'],
            'notes' => ['nullable', 'string'],
        ];
    }
}