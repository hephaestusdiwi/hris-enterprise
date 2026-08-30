<?php

namespace App\Modules\Payroll\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SalarySummaryExport implements FromArray, WithHeadings
{
    public function __construct(private array $summary, private array $filters)
    {
    }

    public function headings(): array
    {
        return ['Periode', 'Jumlah Karyawan', 'Basic Salary', 'Allowance', 'Gross Earning', 'Structural Deduction', 'Manual Deduction', 'BPJS Employee', 'BPJS Company', 'PPh21', 'Loan', 'Net Pay'];
    }

    public function array(): array
    {
        return [[
            $this->filters['period_month'].'/'.$this->filters['period_year'],
            $this->summary['employee_count'],
            $this->summary['basic_salary'],
            $this->summary['allowance_total'],
            $this->summary['gross_earning'],
            $this->summary['structural_deduction'],
            $this->summary['manual_deduction_total'],
            $this->summary['bpjs_employee_total'],
            $this->summary['bpjs_employer_total'],
            $this->summary['tax_amount'],
            $this->summary['loan_deduction_total'],
            $this->summary['net_pay'],
        ]];
    }
}
