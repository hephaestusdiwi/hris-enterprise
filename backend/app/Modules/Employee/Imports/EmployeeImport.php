<?php

namespace App\Modules\Employee\Imports;

use App\Modules\Branch\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Department\Models\Department;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Services\EmployeeService;
use App\Modules\EmploymentStatus\Models\EmploymentStatus;
use App\Modules\EmploymentType\Models\EmploymentType;
use App\Modules\JobLevel\Models\JobLevel;
use App\Modules\Position\Models\Position;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * "Bulk Add Employee" -- import karyawan BARU. Setiap baris valid diproses
 * lewat EmployeeService::createWithUserAccount() yang sudah ada (Store
 * flow existing) -- TIDAK duplicate logic User provisioning/leave balance
 * generation apapun di sini.
 *
 * FK (company/branch/dst) di-resolve dari CODE, bukan raw ID -- lebih
 * manusiawi buat diisi HR di Excel.
 */
class EmployeeImport implements ToCollection, WithHeadingRow
{
    public array $created = [];
    public array $errors = [];

    public function __construct(private EmployeeService $employeeService)
    {
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            try {
                $this->processRow($row, $rowNumber);
            } catch (\Throwable $e) {
                $this->errors[] = "Baris {$rowNumber}: {$e->getMessage()}";
            }
        }
    }

    private function processRow(Collection $row, int $rowNumber): void
    {
        $employeeNumber = trim((string) ($row['employee_number'] ?? ''));
        $firstName = trim((string) ($row['first_name'] ?? ''));
        $gender = trim((string) ($row['gender'] ?? ''));
        $companyCode = trim((string) ($row['company_code'] ?? ''));
        $joinDate = trim((string) ($row['join_date'] ?? ''));
        $userEmail = trim((string) ($row['user_email'] ?? ''));

        if ($employeeNumber === '' || $firstName === '' || $gender === '' || $companyCode === '' || $joinDate === '' || $userEmail === '') {
            $this->errors[] = "Baris {$rowNumber}: employee_number, first_name, gender, company_code, join_date, dan user_email wajib diisi.";

            return;
        }

        if (Employee::where('employee_number', $employeeNumber)->whereNull('deleted_at')->exists()) {
            $this->errors[] = "Baris {$rowNumber}: employee_number '{$employeeNumber}' sudah dipakai.";

            return;
        }

        $company = Company::where('code', $companyCode)->first();

        if (! $company) {
            $this->errors[] = "Baris {$rowNumber}: company_code '{$companyCode}' tidak ditemukan.";

            return;
        }

        // Resolve semua FK opsional dulu -- kalau ADA yang gagal (kode gak
        // ketemu), error-nya udah kecatet di $this->errors di dalam
        // resolveOptional(), tinggal cek apa jumlah error nambah abis ini.
        $errorCountBefore = count($this->errors);

        $branch = $this->resolveOptional(Branch::class, $row['branch_code'] ?? null, $company->id, $rowNumber, 'branch_code');
        $department = $this->resolveOptional(Department::class, $row['department_code'] ?? null, $company->id, $rowNumber, 'department_code');
        $position = $this->resolveOptional(Position::class, $row['position_code'] ?? null, $company->id, $rowNumber, 'position_code');
        $jobLevel = $this->resolveOptional(JobLevel::class, $row['job_level_code'] ?? null, $company->id, $rowNumber, 'job_level_code');
        $employmentType = $this->resolveOptional(EmploymentType::class, $row['employment_type_code'] ?? null, $company->id, $rowNumber, 'employment_type_code');
        $employmentStatus = $this->resolveOptional(EmploymentStatus::class, $row['employment_status_code'] ?? null, null, $rowNumber, 'employment_status_code');

        if (count($this->errors) > $errorCountBefore) {
            return; // salah satu kode FK gak ketemu, error sudah tercatat
        }

        $managerEmployeeId = null;
        $managerEmployeeNumber = trim((string) ($row['manager_employee_number'] ?? ''));

        if ($managerEmployeeNumber !== '') {
            $manager = Employee::where('employee_number', $managerEmployeeNumber)->first();

            if (! $manager) {
                $this->errors[] = "Baris {$rowNumber}: manager_employee_number '{$managerEmployeeNumber}' tidak ditemukan.";

                return;
            }

            $managerEmployeeId = $manager->id;
        }

        $data = [
            'employee_number' => $employeeNumber,
            'company_id' => $company->id,
            'branch_id' => $branch?->id,
            'department_id' => $department?->id,
            'position_id' => $position?->id,
            'job_level_id' => $jobLevel?->id,
            'employment_type_id' => $employmentType?->id,
            'employment_status_id' => $employmentStatus?->id,
            'manager_employee_id' => $managerEmployeeId,
            'new_user' => ['email' => $userEmail],
            'join_date' => $joinDate,
            'resign_date' => $this->blankToNull($row['resign_date'] ?? null),
            'contract_start_date' => $this->blankToNull($row['contract_start_date'] ?? null),
            'contract_end_date' => $this->blankToNull($row['contract_end_date'] ?? null),
            'probation_end_date' => $this->blankToNull($row['probation_end_date'] ?? null),
            'first_name' => $firstName,
            'last_name' => $this->blankToNull($row['last_name'] ?? null),
            'gender' => $gender,
            'birth_place' => $this->blankToNull($row['birth_place'] ?? null),
            'birth_date' => $this->blankToNull($row['birth_date'] ?? null),
            'marital_status' => $this->blankToNull($row['marital_status'] ?? null),
            'phone' => $this->blankToNull($row['phone'] ?? null),
            'personal_email' => $this->blankToNull($row['personal_email'] ?? null),
            'address' => $this->blankToNull($row['address'] ?? null),
            'emergency_contact_name' => $this->blankToNull($row['emergency_contact_name'] ?? null),
            'emergency_contact_phone' => $this->blankToNull($row['emergency_contact_phone'] ?? null),
            'national_id_number' => $this->blankToNull($row['national_id_number'] ?? null),
            'tax_number' => $this->blankToNull($row['tax_number'] ?? null),
            'bank_name' => $this->blankToNull($row['bank_name'] ?? null),
            'bank_account_number' => $this->blankToNull($row['bank_account_number'] ?? null),
            'bank_account_holder_name' => $this->blankToNull($row['bank_account_holder_name'] ?? null),
        ];

        try {
            $result = $this->employeeService->createWithUserAccount($data);
            $this->created[] = $result['employee']->employee_number;
        } catch (ValidationException $e) {
            $this->errors[] = "Baris {$rowNumber}: ".collect($e->errors())->flatten()->first();
        } catch (\Throwable $e) {
            $this->errors[] = "Baris {$rowNumber}: {$e->getMessage()}";
        }
    }

    private function resolveOptional(string $modelClass, ?string $code, ?int $companyId, int $rowNumber, string $columnName): mixed
    {
        $code = trim((string) $code);

        if ($code === '') {
            return null;
        }

        $query = $modelClass::where('code', $code);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $record = $query->first();

        if (! $record) {
            $this->errors[] = "Baris {$rowNumber}: {$columnName} '{$code}' tidak ditemukan.";
        }

        return $record;
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}