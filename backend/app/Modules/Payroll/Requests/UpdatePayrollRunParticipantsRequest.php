<?php

namespace App\Modules\Payroll\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollRunParticipantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
        ];
    }
}