<?php
 
namespace App\Modules\Employee\Imports;
 
use App\Modules\Branch\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Department\Models\Department;
use App\Modules\Employee\Models\Employee;
use App\Modules\EmploymentStatus\Models\EmploymentStatus;
use App\Modules\EmploymentType\Models\EmploymentType;
use App\Modules\JobLevel\Models\JobLevel;
use App\Modules\Position\Models\Position;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
 
/**
 * "Bulk Update Data" -- update employee yang SUDAH ADA, dicocokkan lewat
 * employee_number. Template-nya = hasil EmployeeExport (kolom sama persis),
 * jadi HR export dulu, edit di Excel, upload lagi ke sini.
 *
 * ATURAN PALING PENTING: 9 field lifecycle-controlled (company/branch/
 * department/position/job_level/manager/employment_type/employment_status/
 * resign_date) di sistem ini WAJIB lewat Employee Movement -- lihat
 * UpdateEmployeeRequest::LIFECYCLE_CONTROLLED_FIELDS & assertLifecycleFieldsUnchanged().
 * Bulk Update ini SENGAJA meniru guard yang SAMA PERSIS: kalau baris Excel
 * mencoba UBAH salah satu dari 9 field itu (beda dari nilai employee saat
 * ini), BARIS ITU DITOLAK SELURUHNYA, bukan cuma field itu yang di-skip --
 * biar gak ada partial-update yang bikin data campur aduk. Kalau nilainya
 * SAMA (no-op, misal HR gak sengaja re-export lalu upload ulang), itu aman,
 * bukan dianggap error. Field non-lifecycle tetap bisa diupdate normal.
 */
class EmployeeBulkUpdateImport implements ToCollection, WithHeadingRow
{
    /**
     * SAMA PERSIS dengan UpdateEmployeeRequest::LIFECYCLE_CONTROLLED_FIELDS.
     */
    private const LIFECYCLE_CONTROLLED_FIELDS = [
        'company_id', 'branch_id', 'department_id', 'position_id', 'job_level_id',
        'manager_employee_id', 'employment_type_id', 'employment_status_id', 'resign_date',
    ];

    public array $updated = [];
    public array $errors = [];

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

        if ($employeeNumber === '') {
            $this->errors[] = "Baris {$rowNumber}: employee_number wajib diisi";

            return;
        }

        $employee = Employee::where('employee_number', $employeeNumber)->first();

        if (! $employee) {
            $this->errors[] = "Baris {$rowNumber}: employee_number '{$employeeNumber}' tidak ditemukan.";
 
            return;
        }

        $lifecycleCandidate = $this->buildLifecycleCandidate($row, $employee);

        foreach (self::LIFECYCLE_CONTROLLED_FIELDS as $field) {
            $submitted = $this->normalize($lifecycleCandidate[$field]);
            $current = $this->normalize($employee->{$field});

            if ($submitted !== $current) {
                $this->errors[] = "Baris {$rowNumber}: field '{$field}' cuma bisa diubah lewat Employee Movement, bukan lewat Bulk Update. Baris ini di-skip seluruhnya.";

                return;
            }
        }

        $updateData = $this->buildNonLifecycleUpdateData($row);

        if (empty($updateData)) {
            return; // gak ada field non-lifecycle yang diisi, gak ada yang perlu diupdate
        }

        $employee->update($updateData);
        $this->update[] = $employeeNumber;
    }

    /**
     * Resolve kolom lifecycle dari CODE di baris Excel jadi ID -- kalau
     * kolomnya kosong di Excel, dianggap "tidak diubah" (pakai nilai
     * employee saat ini), BUKAN diset jadi null. Ini penting supaya HR
     * yang cuma mau update field non-lifecycle (misal nomor HP) gak perlu
     * isi ulang SEMUA kolom lifecycle di tiap baris.
     */
    private function buildLifecycleCandidate(Collection $row, Employee $employee): array
    {
        $companyCode = trim((string) ($row['company_code'] ?? ''));
        $company = $companyCode !== '' ? Company::where('code', $companyCode)->first() : null;
        $companyId = $company?->id ?? $employee->company_id;
 
        return [
            'company_id' => $companyId,
            'branch_id' => $this->resolveCodeOrKeepCurrent(Branch::class, $row['branch_code'] ?? null, $companyId, $employee->branch_id),
            'department_id' => $this->resolveCodeOrKeepCurrent(Department::class, $row['department_code'] ?? null, $companyId, $employee->department_id),
            'position_id' => $this->resolveCodeOrKeepCurrent(Position::class, $row['position_code'] ?? null, $companyId, $employee->position_id),
            'job_level_id' => $this->resolveCodeOrKeepCurrent(JobLevel::class, $row['job_level_code'] ?? null, $companyId, $employee->job_level_id),
            'manager_employee_id' => $this->resolveManagerOrKeepCurrent($row['manager_employee_number'] ?? null, $employee),
            'employment_type_id' => $this->resolveCodeOrKeepCurrent(EmploymentType::class, $row['employment_type_code'] ?? null, $companyId, $employee->employment_type_id),
            'employment_status_id' => $this->resolveCodeOrKeepCurrent(EmploymentStatus::class, $row['employment_status_code'] ?? null, null, $employee->employment_status_id),
            'resign_date' => $this->blankMeansKeepCurrent($row['resign_date'] ?? null, $employee->resign_date),
        ];
    }

    private function resolveCodeOrKeepCurrent(string $modelClass, ?string $code, ?int $companyId, ?int $currentId): ?int
    {
        $code = trim((string) $code);

        if ($code === '') {
            return $currentId; // kosong di Excel = tidak diubah
        }

        $query = $modelClass::where('code', $code);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query->first()?->id ?? $currentId;
    }

    private function resolveManagerOrKeepCurrent(?string $managerEmployeeNumber, Employee $employee): ?int 
    {
        $managerEmployeeNumber = trim((string) $managerEmployeeNumber);

        if ($managerEmployeeNumber === '') {
            return $employee->manager_employee_id;
        }

        return Employee::where('employee_number', $managerEmployeeNumber)->first()?->id ?? $employee->manager_employee_id;
    }

    private function blankMeansKeepCurrent(?string $value, mixed $current): mixed
    {
        $value = trim((string) $value);

        return $value === '' ? $current : $value;
    }

    /**
     * Field non-lifecycle -- HANYA diikutkan ke update() kalau kolomnya
     * BENERAN diisi di Excel (blank = tidak disentuh, partial update).
     */
    private function buildNonLifecycleUpdateData(Collection $row): array
    {
        $fields = [
            'first_name', 'last_name', 'gender', 'birth_place', 'birth_date', 'marital_status',
            'phone', 'personal_email', 'address', 'emergency_contact_name', 'emergency_contact_phone',
            'national_id_number', 'tax_number', 'bank_name', 'bank_account_number', 'bank_account_holder_name',
            'contact_start_date', 'contract_end_date', 'probation_end_date';
        ];

        $data = [];

        foreach ($fields as $field) {
            $value = trim((string) ($row[$field] ?? ''));

            if ($value !== '') {
                $data[$field] = $value;
            }
        }

        return $data;
    }

    private function normalize(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        return (string) $value;
    }
}
