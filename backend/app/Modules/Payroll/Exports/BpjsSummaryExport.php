<?php

namespace App\Modules\Payroll\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class BpjsSummaryExport implements FromArray, WithHeadings
{
    public function __construct(private array $summary, private array $filters)
    {
    }

    public function headings(): array
    {
        return [
            'Periode', 'Jumlah Karyawan',
            'Kesehatan (Karyawan)', 'Kesehatan (Company)',
            'JHT (Karyawan)', 'JHT (Company)',
            'JKK (Company)', 'JKM (Company)',
            'Total Employee', 'Total Company',
        ];
    }

    public function array(): array
    {
        return [[
            $this->filters['period_month'].'/'.$this->filters['period_year'],
            $this->summary['employee_count'],
            $this->summary['kesehatan_employee'],
            $this->summary['kesehatan_employer'],
            $this->summary['jht_employee'],
            $this->summary['jht_employer'],
            $this->summary['jkk_employer'],
            $this->summary['jkm_employer'],
            $this->summary['bpjs_employee_total'],
            $this->summary['bpjs_employer_total'],
        ]];
    }
}
