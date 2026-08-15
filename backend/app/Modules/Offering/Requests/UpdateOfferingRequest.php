<?php

namespace App\Modules\Offering\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOfferingRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'proposed_start_date' => ['sometimes', 'date'],
            'proposed_salary' => ['nullable', 'numeric', 'min:0'],
            'compensation_notes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}