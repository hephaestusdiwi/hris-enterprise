<?php

namespace App\Modules\Payroll\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'period_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
            'cutoff_date' => ['nullable', 'date'],
            'payment_date' => ['nullable', 'date'],
            'employee_ids' => ['required', 'array', 'min:1'],
            // Employee HARUS berasal dari company yang sama dengan payroll run
            // (bukan cuma exists di tabel employees manapun) — cegah payroll
            // run kena isi peserta lintas company.
            'employee_ids.*' => [
                'integer',
                Rule::exists('employees', 'id')->where(
                    fn ($query) => $query->where('company_id', $this->input('company_id'))
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