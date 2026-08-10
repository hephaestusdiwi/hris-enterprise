<?php

namespace App\Modules\Employee\Requests;

use App\Modules\Employee\Models\Employee;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Sejak Employee Movement (Phase 2) tersedia, 9 field lifecycle di bawah ini
 * TIDAK BOLEH lagi diubah lewat endpoint ini — HARUS lewat
 * POST /employees/{employee}/movements, supaya history & approval-nya
 * selalu tercatat (selaras dokumentasi Mekari Talenta: perubahan
 * Position/Org/Level/Branch wajib lewat fitur Transfer, bukan edit biasa).
 *
 * Field-field itu SENGAJA tidak dihapus dari payload secara diam-diam —
 * kalau nilainya sama dengan current state (no-op / frontend lama masih
 * echo balik nilai existing), request tetap lolos. Kalau ada percobaan
 * BENAR-BENAR mengubah nilainya lewat endpoint ini, request ditolak (422)
 * dengan pesan yang mengarahkan ke Employee Movement — bukan silent-drop
 * seperti bug sebelumnya, dan bukan juga langsung 500/breaking existing
 * caller yang masih resend nilai lama.
 */
class UpdateEmployeeRequest extends FormRequest
{
    private const LIFECYCLE_CONTROLLED_FIELDS = [
        'company_id',
        'branch_id',
        'department_id',
        'position_id',
        'job_level_id',
        'manager_employee_id',
        'employment_type_id',
        'employment_status_id',
        'resign_date',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_number' => [
                'required', 'string', 'max:50',
                Rule::unique('employees', 'employee_number')
                    ->ignore($this->route('employee'))
                    ->whereNull('deleted_at'),
            ],
            'working_schedule_id' => ['nullable', 'exists:working_schedules,id'],

            // Employee WAJIB punya User (Architecture Decision) — boleh direassign
            // ke User lain, tapi tidak boleh dikosongkan.
            'user_id' => ['required', 'integer', 'exists:users,id'],

            'join_date' => ['required', 'date'],
            'contract_start_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:contract_start_date'],
            // Probation adalah fase employment berdasarkan tanggal ini, independen
            // dari Employment Type — berlaku untuk employment type apapun.
            // CATATAN: berbeda dari 9 field lifecycle-controlled di atas, 3 field
            // ini (contract_start_date/contract_end_date/probation_end_date) BELUM
            // dikunci ke Employee Movement — mengikuti daftar eksplisit yang
            // disetujui. Perlu keputusan lanjutan apakah ini juga perlu dikunci,
            // karena movement_type contract_change/probation_confirmed sudah ada.
            'probation_end_date' => ['nullable', 'date'],

            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'marital_status' => ['nullable', 'in:single,married,divorced,widowed'],

            'phone' => ['nullable', 'string', 'max:30'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],

            'national_id_number' => [
                'nullable', 'string', 'max:50',
                Rule::unique('employees', 'national_id_number')
                    ->ignore($this->route('employee'))
                    ->whereNull('deleted_at'),
            ],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_account_holder_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Employee $employee */
            $employee = $this->route('employee');

            $userId = $this->input('user_id');

            if ($userId) {
                $alreadyLinked = Employee::where('user_id', $userId)
                    ->where('id', '!=', $employee->id)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($alreadyLinked) {
                    $validator->errors()->add('user_id', 'User ini sudah terhubung dengan Employee lain yang aktif.');
                }
            }

            $this->assertLifecycleFieldsUnchanged($validator, $employee);
            $this->validateContractDatesAgainstEmploymentType($validator, $employee);
        });
    }

    private function assertLifecycleFieldsUnchanged(Validator $validator, Employee $employee): void
    {
        foreach (self::LIFECYCLE_CONTROLLED_FIELDS as $field) {
            if (! $this->has($field)) {
                continue; // tidak dikirim sama sekali — tidak ada percobaan ubah
            }

            $submitted = $this->normalizeLifecycleValue($this->input($field));
            $current = $this->normalizeLifecycleValue($employee->{$field});

            if ($submitted !== $current) {
                $validator->errors()->add(
                    $field,
                    "Field '{$field}' hanya bisa diubah lewat Employee Movement (POST /employees/{$employee->id}/movements), bukan lewat Employee Edit."
                );
            }
        }
    }

    private function normalizeLifecycleValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        return (string) $value;
    }

    /**
     * Sama seperti StoreEmployeeRequest — kalau Employment Type-nya CONTRACT,
     * contract_start_date & contract_end_date wajib diisi. employment_type_id
     * dibaca dari CURRENT state Employee (bukan input request), karena field
     * itu sekarang lifecycle-controlled — tidak bisa berubah lewat request ini.
     */
    private function validateContractDatesAgainstEmploymentType(Validator $validator, Employee $employee): void
    {
        $type = $employee->employmentType;

        if (! $type || $type->code !== 'CONTRACT') {
            return;
        }

        if (! $this->filled('contract_start_date')) {
            $validator->errors()->add(
                'contract_start_date',
                'Contract Start Date wajib diisi untuk Employment Type Contract.'
            );
        }

        if (! $this->filled('contract_end_date')) {
            $validator->errors()->add(
                'contract_end_date',
                'Contract End Date wajib diisi untuk Employment Type Contract.'
            );
        }
    }
}
