<?php

namespace App\Modules\EmployeeAllowance\Exports;

use App\Modules\EmployeeAllowance\Models\EmployeeAllowance;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeeAllowanceExport implements FromCollection, WithHeadings
{
    public function __construct(private Collection $allowances)
    {
    }

    public function headings(): array
    {
        return [
            'No. Karyawan', 'Nama Employee', 'Komponen', 'Tahun', 'Bulan', 'Amount', 'Remark', 'Status',
        ];
    }

    public function collection(): Collection
    {
        return $this->allowances->map(fn (EmployeeAllowance $a) => [
            $a->employee->employee_number,
            trim("{$a->employee->first_name} {$a->employee->last_name}"),
            $a->salaryComponent->name,
            $a->payroll_period_year,
            $a->payroll_period_month,
            $a->amount,
            $a->remark,
            $a->status->value,
        ]);
    }
}