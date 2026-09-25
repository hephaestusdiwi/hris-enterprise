<?php

namespace App\Modules\Payroll\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AnnualTaxReconciliationExport implements FromArray, WithHeadings
{
    public function __construct(private Collection $rows)
    {
    }

    public function headings(): array
    {
        return [
            'No. Karyawan', 'Nama', 'Tahun Pajak', 'Metode Pajak',
            'Penghasilan Bruto Setahun', 'Biaya Jabatan/Pensiun', 'Iuran Pensiun/JHT',
            'Penghasilan Neto', 'PTKP', 'PKP', 'PPh Pasal 17 Setahun',
            'PPh Sudah Dipotong (Masa Sebelumnya)', 'Kurang/(Lebih) Bayar Masa Terakhir', 'Status',
        ];
    }

    public function array(): array
    {
        return $this->rows->map(fn ($row) => [
            $row->employee_number,
            trim($row->first_name.' '.$row->last_name),
            $row->tax_year,
            $row->tax_method_applied?->value ?? $row->tax_method_applied,
            $row->total_gross_annual,
            $row->position_cost_deduction,
            $row->pension_deduction,
            $row->net_annual_income,
            $row->ptkp_amount,
            $row->pkp,
            $row->annual_tax_pasal17,
            $row->total_withheld_prior_months,
            $row->final_period_adjustment,
            $row->status(),
        ])->all();
    }
}
