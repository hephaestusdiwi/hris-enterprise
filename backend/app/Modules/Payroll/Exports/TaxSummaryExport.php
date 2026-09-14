<?php

namespace App\Modules\Payroll\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TaxSummaryExport implements FromArray, WithHeadings
{
    public function __construct(private array $summary, private array $filters)
    {
    }

    public function headings(): array
    {
        return ['Periode', 'Jumlah Karyawan', 'Gross Earning', 'Total PPh21'];
    }

    public function array(): array
    {
        return [[
            $this->filters['period_month'].'/'.$this->filters['period_year'],
            $this->summary['employee_count'],
            $this->summary['gross_earning'],
            $this->summary['tax_amount'],
        ]];
    }
}
