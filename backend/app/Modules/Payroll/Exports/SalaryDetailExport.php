<?php

namespace App\Modules\Payroll\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SalaryDetailExport implements FromArray, WithHeadings
{
    public function __construct(private Collection $rows)
    {
    }

    public function headings(): array
    {
        return [
            'No. Karyawan', 'Nama', 'Basic Salary', 'Allowance', 'Gross Earning',
            'Structural Deduction', 'Manual Deduction', 'BPJS Employee', 'BPJS Company',
            'PPh21', 'Loan', 'Net Pay',
        ];
    }

    public function array(): array
    {
        return $this->rows->map(fn ($row) => [
            $row->employee_number,
            trim($row->first_name.' '.$row->last_name),
            $row->basic_salary,
            $row->allowance_total,
            $row->gross_earning,
            $row->structural_deduction,
            $row->manual_deduction_total,
            $row->bpjs_employee_total,
            $row->bpjs_employer_total,
            $row->tax_amount,
            $row->loan_deduction_total,
            $row->net_pay,
        ])->all();
    }
}
