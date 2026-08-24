<?php

namespace App\Modules\Attendance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkAbsenceAsTimeOffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // sudah digate permission 'manage absence deductions' di route
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'date' => ['required', 'date'],
            'leave_type_id' => ['required', 'exists:leave_types,id'],
        ];
    }
}