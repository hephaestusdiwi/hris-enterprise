<?php

namespace App\Modules\Payroll\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePayrollRunParticipantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Route model binding {payrollRun} sudah resolve duluan sebelum FormRequest
        // ini divalidasi — company_id diambil dari run yang bersangkutan, bukan input.
        $companyId = $this->route('payrollRun')?->company_id;

        return [
            'employee_ids' => ['required', 'array', 'min:1'],
            // Employee HARUS berasal dari company yang sama dengan payroll run ini.
            'employee_ids.*' => [
                'integer',
                Rule::exists('employees', 'id')->where(
                    fn ($query) => $query->where('company_id', $companyId)
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_ids.*.exists' => 'Salah satu employee yang dipilih tidak ditemukan atau bukan berasal dari company ini.',
        ];
    }
}