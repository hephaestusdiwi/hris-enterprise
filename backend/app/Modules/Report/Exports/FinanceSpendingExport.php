<?php

namespace App\Modules\Report\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FinanceSpendingExport implements FromCollection, WithHeadings, WithMapping
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
            'source_type', 'source_id', 'employee_name', 'department_name',
            'category', 'amount', 'status', 'transaction_date',
        ];
    }

    public function map($row): array
    {
        return [
            $row['source_type'],
            $row['source_id'],
            $row['employee_name'],
            $row['department_name'],
            $row['category'],
            $row['amount'],
            $row['status'],
            $row['transaction_date'],
        ];
    }
}