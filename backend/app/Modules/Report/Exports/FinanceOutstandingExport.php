<?php

namespace App\Modules\Report\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FinanceOutstandingExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private Collection $rows)
    {
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'source', 'source_id', 'employee_name', 'department_name', 'reference',
            'original_amount', 'paid_amount', 'outstanding_amount', 'status',
            'relevant_date', 'aging_days',
        ];
    }

    public function map($row): array
    {
        return [
            $row['source'],
            $row['source_id'],
            $row['employee_name'],
            $row['department_name'],
            $row['reference'],
            $row['original_amount'],
            $row['paid_amount'],
            $row['outstanding_amount'],
            $row['status'],
            $row['relevant_date'],
            $row['aging_days'],
        ];
    }
}