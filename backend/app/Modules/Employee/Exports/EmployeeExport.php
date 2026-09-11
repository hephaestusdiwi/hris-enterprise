<?php

namespace App\Modules\Employee\Exports;

use App\Modules\Employee\Models\Employee;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Dipakai untuk 2 tujuan sekaligus (sengaja direuse, bukan duplikat):
 * 1. Export biasa -- lihat data employee saat ini.
 * 2. Template "Bulk Update" -- HR export, edit di Excel, upload lagi lewat
 *    EmployeeController::bulkUpdate(). employee_number di kolom pertama
 *    jadi kunci pencocokan baris pas upload.
 *
 * Kolom sengaja pakai CODE (company_code, department_code, dst) buat FK,
 * bukan raw ID -- lebih manusiawi buat diedit HR di Excel, konsisten sama
 * pola EmployeeImport/EmployeeBulkUpdateImport yang resolve by code juga.
 */
class EmployeeExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private Collection $employees)
    {
    }

    public function collection(): Collection
    {
        return $this->employees;
    }

    public function headings(): array
    {
        return [
            'employee_number', 'first_name', 'last_name', 'gender', 'birth_place', 'birth_date',
            'marital_status', 'phone', 'personal_email', 'address',
            'emergency_contact_name', 'emergency_contact_phone',
            'national_id_number', 'tax_number', 'bank_name', 'bank_account_number', 'bank_account_holder_name',
            'company_code', 'branch_code', 'department_code', 'position_code', 'job_level_code',
            'employment_type_code', 'employment_status_code', 'manager_employee_number',
            'join_date', 'resign_date', 'contract_start_date', 'contract_end_date', 'probation_end_date',
            'user_email',
        ];
    }

    public function map($employee): array
    {
        /** @var Employee $employee */
        return [
            $employee->employee_number,
            $employee->first_name,
            $employee->last_name,
            $employee->gender,
            $employee->birth_place,
            $employee->birth_date?->toDateString(),
            $employee->marital_status,
            $employee->phone,
            $employee->personal_email,
            $employee->address,
            $employee->emergency_contact_name,
            $employee->emergency_contact_phone,
            $employee->national_id_number,
            $employee->tax_number,
            $employee->bank_name,
            $employee->bank_account_number,
            $employee->bank_account_holder_name,
            $employee->company?->code,
            $employee->branch?->code,
            $employee->department?->code,
            $employee->position?->code,
            $employee->jobLevel?->code,
            $employee->employmentType?->code,
            $employee->employmentStatus?->code,
            $employee->manager?->employee_number,
            $employee->join_date?->toDateString(),
            $employee->resign_date?->toDateString(),
            $employee->contract_start_date?->toDateString(),
            $employee->contract_end_date?->toDateString(),
            $employee->probation_end_date?->toDateString(),
            $employee->user?->email,
        ];
    }
}