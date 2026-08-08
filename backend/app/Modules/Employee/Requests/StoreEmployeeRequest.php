<?php

namespace App\Modules\Employee\Requests;

use App\Modules\Employee\Models\Employee;
use App\Modules\EmploymentType\Models\EmploymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Employment Information
            'employee_number' => [
                'required', 'string', 'max:50',
                Rule::unique('employees', 'employee_number')->whereNull('deleted_at'),
            ],
            'company_id' => ['required', 'exists:companies,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'job_level_id' => ['nullable', 'exists:job_levels,id'],
            'working_schedule_id' => ['nullable', 'exists:working_schedules,id'],
            'employment_status_id' => ['nullable', 'exists:employment_statuses,id'],
            'employment_type_id' => ['nullable', 'exists:employment_types,id'],
            'manager_employee_id' => ['nullable', 'exists:employees,id'],

            // User Account — WAJIB salah satu (lihat withValidator() di bawah):
            // - "user_id": link ke User yang sudah ada
            // - "new_user.email" (+ optional "new_user.password"): auto-provision User baru
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'new_user' => ['nullable', 'array'],
            'new_user.email' => ['required_with:new_user', 'email', 'max:255', 'unique:users,email'],
            'new_user.password' => ['nullable', 'string', 'min:8'],

            'join_date' => ['required', 'date'],
            'resign_date' => ['nullable', 'date', 'after_or_equal:join_date'],
            'contract_start_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:contract_start_date'],
            'probation_end_date' => ['nullable', 'date'],

            // Personal Information
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'marital_status' => ['nullable', 'in:single,married,divorced,widowed'],

            // Contact Information
            'phone' => ['nullable', 'string', 'max:30'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],

            // Identity Information
            'national_id_number' => [
                'nullable', 'string', 'max:50',
                Rule::unique('employees', 'national_id_number')->whereNull('deleted_at'),
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
            $hasExisting = $this->filled('user_id');
            $hasNew = $this->filled('new_user.email');

            if (! $hasExisting && ! $hasNew) {
                $validator->errors()->add(
                    'user_id',
                    'Employee wajib punya User Account: isi "user_id" (akun yang sudah ada) atau "new_user" (buat akun baru).'
                );
            }

            if ($hasExisting && $hasNew) {
                $validator->errors()->add(
                    'user_id',
                    'Pilih salah satu saja: "user_id" ATAU "new_user", jangan dua-duanya.'
                );
            }

            if ($hasExisting) {
                $alreadyLinked = Employee::where('user_id', $this->input('user_id'))
                    ->whereNull('deleted_at')
                    ->exists();

                if ($alreadyLinked) {
                    $validator->errors()->add('user_id', 'User ini sudah terhubung dengan Employee lain yang aktif.');
                }
            }

            $this->validateContractDatesAgainstEmploymentType($validator);
        });
    }

    private function validateContractDatesAgainstEmploymentType(Validator $validator): void
    {
        $employmentTypeId = $this->input('employment_type_id');

        if (! $employmentTypeId) {
            return;
        }

        $type = EmploymentType::find($employmentTypeId);

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