<?php

namespace App\Modules\Payroll\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TaxDetailExport implements FromArray, WithHeadings
{
    public function __construct(private Collection $rows)
    {
    }

    public function headings(): array
    {
        return ['No. Karyawan', 'Nama', 'Gross Earning', 'PPh21', 'Rekonsiliasi Tahunan?'];
    }

    public function array(): array
    {
        return $this->rows->map(fn ($row) => [
            $row->employee_number,
            trim($row->first_name.' '.$row->last_name),
            $row->gross_earning,
            $row->tax_amount,
            $row->is_annual_reconciliation ? 'Ya' : 'Tidak',
        ])->all();
    }
}
