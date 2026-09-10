<?php

namespace App\Modules\Payroll\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class BpjsDetailExport implements FromArray, WithHeadings
{
    public function __construct(private Collection $rows)
    {
    }

    public function headings(): array
    {
        return [
            'No. Karyawan', 'Nama', 'NPP',
            'Kesehatan (Karyawan)', 'Kesehatan (Company)',
            'JHT (Karyawan)', 'JHT (Company)',
            'JKK (Company)', 'JKM (Company)',
            'Total Employee', 'Total Company',
        ];
    }

    public function array(): array
    {
        return $this->rows->map(fn ($row) => [
            $row->employee_number,
            trim($row->first_name.' '.$row->last_name),
            $row->npp_number,
            $row->kesehatan_employee,
            $row->kesehatan_employer,
            $row->jht_employee,
            $row->jht_employer,
            $row->jkk_employer,
            $row->jkm_employer,
            $row->bpjs_employee_total,
            $row->bpjs_employer_total,
        ])->all();
    }
}
