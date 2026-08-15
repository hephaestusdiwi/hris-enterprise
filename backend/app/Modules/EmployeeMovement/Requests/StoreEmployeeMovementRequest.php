<?php

namespace App\Modules\EmployeeMovement\Requests;

use App\Modules\EmployeeMovement\Enums\EmployeeMovementType;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Object-level (boleh bikin movement untuk employee ini atau tidak)
        // dicek EmployeePolicy::view via Controller, bukan di sini.
        return $this->user()?->can('create employee movements') ?? false;
    }

    public function rules(): array
    {
        return [
            'movement_type' => ['required', 'string', 'in:'.implode(',', array_column(EmployeeMovementType::cases(), 'value'))],
            'effective_date' => ['required', 'date'],
            'reason' => ['nullable', 'string'],

            // after_values yang RELEVAN per movement_type ditentukan oleh
            // EmployeeMovementType::relevantFields() (dipakai di afterValues()
            // di bawah) — field lain yang dikirim tapi tidak relevan untuk
            // movement_type ini diabaikan begitu saja, bukan error.
            'company_id' => ['nullable', 'exists:companies,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'job_level_id' => ['nullable', 'exists:job_levels,id'],
            'manager_employee_id' => ['nullable', 'exists:employees,id'],
            'employment_type_id' => ['nullable', 'exists:employment_types,id'],
            'employment_status_id' => ['nullable', 'exists:employment_statuses,id'],
            'contract_start_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:contract_start_date'],
            'probation_end_date' => ['nullable', 'date'],
            'resign_date' => ['nullable', 'date'],
            'join_date' => ['nullable', 'date'],
        ];
    }

    // Catatan: sebelumnya ada withValidator() di sini yang memaksa semua
    // relevantFields() wajib diisi — itu SALAH, karena branch_id/department_id/
    // position_id/manager_employee_id memang nullable di Employee (mis.
    // transfer JADI tidak punya manager). rules() di atas sudah cukup.

    /**
     * Cuma field yang BENAR-BENAR dikirim di request yang masuk sini —
     * SENGAJA tidak default-kan field relevan yang tidak dikirim ke null.
     * EmployeeMovementService yang menentukan: field yang tidak ada di sini
     * artinya "tidak diubah, pertahankan nilai current Employee", BUKAN
     * "kosongkan". Ini penting buat kasus seperti Extend Contract yang cuma
     * kirim contract_end_date — employment_type_id/contract_start_date
     * TIDAK BOLEH ikut ke-null-kan gara-gara tidak disertakan.
     *
     * @return array<string, mixed>
     */
    public function afterValues(): array
    {
        $type = EmployeeMovementType::from($this->validated('movement_type'));

        return collect($type->relevantFields())
            ->filter(fn (string $field) => $this->has($field))
            ->mapWithKeys(fn (string $field) => [$field => $this->input($field)])
            ->all();
    }
}
