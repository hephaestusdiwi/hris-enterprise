<?php

namespace App\Modules\EmployeeDeduction\Exports;

use App\Modules\EmployeeDeduction\Models\EmployeeDeduction;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeeDeductionExport implements FromCollection, WithHeadings
{
    public function __construct(private Collection $deductions)
    {
    }

    public function headings(): array
    {
        return ['No. Karyawan', 'Nama Employee', 'Komponen', 'Tahun', 'Bulan', 'Amount', 'Remark', 'Status'];
    }

    public function collection(): Collection
    {
        return $this->deductions->map(fn (EmployeeDeduction $d) => [
            $d->employee->employee_number,
            trim("{$d->employee->first_name} {$d->employee->last_name}"),
            $d->salaryComponent->name,
            $d->payroll_period_year,
            $d->payroll_period_month,
            $d->amount,
            $d->remark,
            $d->status->value,
        ]);
    }
}