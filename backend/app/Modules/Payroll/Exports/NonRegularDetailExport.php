<?php

namespace App\Modules\Payroll\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class NonRegularDetailExport implements FromArray, WithHeadings
{
    public function __construct(private Collection $rows)
    {
    }

    public function headings(): array
    {
        return [
            'No. Karyawan', 'Nama', 'Component', 'Kategori', 'Amount',
            'Earning/Deduction', 'Taxable', 'Net Payslip', 'Status',
        ];
    }

    public function array(): array
    {
        return $this->rows->map(fn ($row) => [
            $row->employee_number,
            trim($row->first_name.' '.$row->last_name),
            $row->component_name,
            $row->category,
            $row->amount,
            $row->is_addition ? 'Earning' : 'Deduction',
            $row->is_taxable ? 'Ya' : 'Tidak',
            $row->net_pay,
            $row->status?->value ?? $row->status,
        ])->all();
    }
}