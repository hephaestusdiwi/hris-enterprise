<?php

namespace App\Modules\Attendance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReverseAbsenceDeductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // sudah digate permission 'manage absence deductions' di route
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string'],
        ];
    }
}