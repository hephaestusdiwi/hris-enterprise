<?php

namespace App\Modules\NewJoiner\Services;

use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Requests\StoreEmployeeRequest;
use App\Modules\Employee\Services\EmployeeService;
use App\Modules\EmploymentType\Models\EmploymentType;
use App\Modules\NewJoiner\Enums\NewJoinerStatus;
use App\Modules\NewJoiner\Exceptions\NewJoinerValidationException;
use App\Modules\NewJoiner\Models\NewJoiner;
use App\Modules\Offering\Enums\OfferingStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class NewJoinerConversionService
{
    public function __construct(
        private EmployeeService $employeeService,
    ) {
    }

    /**
     * @param array{
     *     job_level_id?: ?int,
     *     working_schedule_id?: ?int,
     *     employment_status_id?: ?int,
     *     manager_employee_id?: ?int,
     *     contract_start_date?: ?string,
     *     contract_end_date?: ?string
     * } $organizationOverrides
     *
     * Field organisasi yang memang belum bisa diisi dari Recruitment —
     * HR lengkapi saat trigger action ini.
     */
    public function convertToEmployee(
        NewJoiner $newJoiner,
        array $organizationOverrides = []
    ): Employee {
        $this->assertEligible($newJoiner);

        $candidate = $newJoiner->candidate;
        $vacancy = $candidate->jobVacancy;

        $offering = $candidate
            ->offerings()
            ->where('status', OfferingStatus::Accepted->value)
            ->latest()
            ->first();

        if (! $offering) {
            throw new NewJoinerValidationException(
                'Tidak ditemukan Offering Accepted untuk Candidate ini.'
            );
        }

        [$firstName, $lastName] = $this->splitName($candidate->full_name);

        $data = [
            'company_id' => $vacancy->company_id,
            'branch_id' => $vacancy->branch_id,
            'department_id' => $vacancy->department_id,
            'position_id' => $vacancy->position_id,
            'job_level_id' => $organizationOverrides['job_level_id'] ?? null,
            'working_schedule_id' => $organizationOverrides['working_schedule_id'] ?? null,
            'employment_status_id' => $organizationOverrides['employment_status_id'] ?? null,
            'employment_type_id' => $vacancy->employment_type_id,
            'manager_employee_id' => $organizationOverrides['manager_employee_id'] ?? null,
            'contract_start_date' => $organizationOverrides['contract_start_date'] ?? null,
            'contract_end_date' => $organizationOverrides['contract_end_date'] ?? null,

            'new_user' => [
                'email' => $candidate->email,
            ],

            'join_date' => $offering->proposed_start_date->toDateString(),

            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => $newJoiner->gender,
            'birth_place' => $newJoiner->birth_place,
            'birth_date' => $newJoiner->birth_date?->toDateString(),
            'marital_status' => $newJoiner->marital_status,
            'phone' => $candidate->phone,
            'personal_email' => $candidate->email,
            'address' => $newJoiner->address,
            'emergency_contact_name' => $newJoiner->emergency_contact_name,
            'emergency_contact_phone' => $newJoiner->emergency_contact_phone,
            'national_id_number' => $newJoiner->national_id_number,
            'tax_number' => $newJoiner->tax_number,
            'bank_name' => $newJoiner->bank_name,
            'bank_account_number' => $newJoiner->bank_account_number,
            'bank_account_holder_name' => $newJoiner->bank_account_holder_name,
        ];

        $this->validateAgainstStoreEmployeeRequest($data);

        return DB::transaction(function () use ($newJoiner, $candidate, $data) {
            $result = $this->employeeService->createWithUserAccount($data);

            $employee = $result['employee'];

            $candidate->update([
                'converted_employee_id' => $employee->id,
            ]);

            $newJoiner->update([
                'employee_id' => $employee->id,
            ]);

            return $employee;
        });
    }

    private function assertEligible(NewJoiner $newJoiner): void
    {
        if (
            $newJoiner->status !== NewJoinerStatus::Submitted
            || ! $newJoiner->ready_for_employee_at
        ) {
            throw new NewJoinerValidationException(
                'New Joiner harus Submitted dan sudah melalui "Proceed as employee" terlebih dahulu.'
            );
        }

        if ($newJoiner->employee_id) {
            throw new NewJoinerValidationException(
                'New Joiner ini sudah pernah dikonversi menjadi Employee.'
            );
        }

        if ($newJoiner->candidate->converted_employee_id) {
            throw new NewJoinerValidationException(
                'Candidate ini sudah pernah dikonversi menjadi Employee.'
            );
        }
    }

    private function validateAgainstStoreEmployeeRequest(array $data): void
    {
        $rules = (new StoreEmployeeRequest())->rules();

        // employee_number sekarang dibuat otomatis oleh EmployeeService.
        // Jadi field ini tidak perlu divalidasi sebagai input dari conversion flow.
        unset($rules['employee_number']);

        Validator::make($data, $rules)->validate();

        // Replikasi manual 1 bagian withValidator() yang relevan di jalur ini:
        // Contract employment type wajib memiliki contract start/end date.
        //
        // Cek user_id XOR new_user TIDAK direplikasi karena jalur ini
        // secara struktural selalu menggunakan new_user.
        if (! empty($data['employment_type_id'])) {
            $type = EmploymentType::find($data['employment_type_id']);

            if (
                $type
                && $type->code === 'CONTRACT'
                && (
                    empty($data['contract_start_date'])
                    || empty($data['contract_end_date'])
                )
            ) {
                throw new NewJoinerValidationException(
                    'Employment Type Contract wajib Contract Start/End Date — lengkapi manual sebelum konversi.'
                );
            }
        }
    }

    /** @return array{0: string, 1: ?string} */
    private function splitName(string $fullName): array
    {
        $parts = explode(' ', trim($fullName), 2);

        return [
            $parts[0],
            $parts[1] ?? null,
        ];
    }
}