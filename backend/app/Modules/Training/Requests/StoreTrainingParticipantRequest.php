<?php

namespace App\Modules\Training\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrainingParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}