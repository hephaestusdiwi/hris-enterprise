<?php

namespace App\Modules\Report\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FinanceApprovalAgingExport implements FromCollection, WithHeadings, WithMapping
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
            'current_step_name', 'approver', 'status', 'requested_at', 'aging_days',
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
            $row['current_step_name'],
            $row['approver'],
            $row['status'],
            $row['requested_at'],
            $row['aging_days'],
        ];
    }
}