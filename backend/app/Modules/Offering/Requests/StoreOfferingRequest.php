<?php

namespace App\Modules\Offering\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOfferingRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'candidate_id' => ['required', 'integer', 'exists:candidates,id'],
            'proposed_start_date' => ['required', 'date'],
            'proposed_salary' => ['nullable', 'numeric', 'min:0'],
            'compensation_notes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}