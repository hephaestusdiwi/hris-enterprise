<?php

namespace App\Modules\Employee\Requests;

use App\Modules\Employee\Models\Employee;
use App\Modules\EmploymentType\Models\EmploymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('employees', 'employee_number')
                    ->ignore($this->route('employee'))
                    ->whereNull('deleted_at'),
            ],
            'company_id' => ['required', 'exists:companies,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'job_level_id' => ['nullable', 'exists:job_levels,id'],
            'working_schedule_id' => ['nullable', 'exists:working_schedules,id'],
            'employment_status_id' => ['nullable', 'exists:employment_statuses,id'],
            'manager_employee_id' => [
                'nullable',
                'exists:employees,id',
                function ($attribute, $value, $fail) {
                    if ($value && (int) $value === (int) $this->route('employee')->id) {
                        $fail('Employee tidak dapat menjadi manager dari dirinya sendiri.');
                    }
                },
            ],

            // Employee WAJIB punya User (Architecture Decision) — boleh direassign
            // ke User lain, tapi tidak boleh dikosongkan.
            'user_id' => ['required', 'integer', 'exists:users,id'],

            'join_date' => ['required', 'date'],
            'resign_date' => ['nullable', 'date', 'after_or_equal:join_date'],

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
                'nullable',
                'string',
                'max:50',
                Rule::unique('employees', 'national_id_number')
                    ->ignore($this->route('employee'))
                    ->whereNull('deleted_at'),
            ],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_account_holder_name' => ['nullable', 'string', 'max:255'],
            'employment_type_id' => [
                'nullable',
                'exists:employment_types,id',
            ],

            'contract_start_date' => [
                'nullable',
                'date',
            ],

            'contract_end_date' => [
                'nullable',
                'date',
                'after_or_equal:contract_start_date',
            ],

            'probation_end_date' => [
                'nullable',
                'date',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $userId = $this->input('user_id');

            if ($userId) {
                $alreadyLinked = Employee::where('user_id', $userId)
                    ->where('id', '!=', $this->route('employee')->id)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($alreadyLinked) {
                    $validator->errors()->add(
                        'user_id',
                        'User ini sudah terhubung dengan Employee lain yang aktif.'
                    );
                }
            }

            $this->validateContractDatesAgainstEmploymentType($validator);
        });
    }

    private function validateContractDatesAgainstEmploymentType(
    Validator $validator
    ): void {
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
