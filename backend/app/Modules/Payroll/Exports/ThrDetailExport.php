<?php

namespace App\Modules\Payroll\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ThrDetailExport implements FromArray, WithHeadings
{
    public function __construct(private Collection $rows)
    {
    }

    public function headings(): array
    {
        return [
            'No. Karyawan', 'Nama', 'Masa Kerja (bulan)', 'Basic Salary (Referensi)',
            'THR Amount', 'Deduction (BPJS)', 'PPh21', 'Net THR', 'Status',
        ];
    }

    public function array(): array
    {
        return $this->rows->map(fn ($row) => [
            $row->employee_number,
            trim($row->first_name.' '.$row->last_name),
            $row->service_months,
            $row->basic_salary,
            $row->thr_amount,
            $row->deduction,
            $row->tax_amount,
            $row->net_pay,
            $row->status,
        ])->all();
    }
}