<?php

namespace App\Modules\Report\Services;

use App\Modules\CashAdvance\Models\CashAdvanceRequestItem;
use App\Modules\Expense\Models\ExpenseClaim;
use App\Modules\Reimbursement\Models\ReimbursementRequestItem;
use App\Modules\Report\Support\ReportMath;
use Illuminate\Support\Collection;

/**
 * Read-only aggregation layer -- TIDAK ada model/migration baru. 3 source
 * (Reimbursement, Cash Advance, Expense Claim) punya struktur berbeda:
 *
 * - Expense Claim: 1 claim = 1 kategori langsung (expense_category_id di
 *   tabel claim). Baris report = 1 baris per claim.
 * - Cash Advance: kategori ada di level ITEM (cash_advance_request_items),
 *   1 request bisa punya banyak kategori/amount berbeda. Baris report =
 *   1 baris per ITEM (bukan per request), supaya breakdown kategori akurat.
 * - Reimbursement: sama seperti Cash Advance, kategori ("benefit") ada di
 *   level ITEM (reimbursement_request_items -> reimbursement_benefits).
 *   Baris report = 1 baris per ITEM juga.
 *
 * Tidak ada kolom company_id langsung di ketiga tabel source -- company
 * diturunkan lewat employee_id -> employees.company_id, jadi semua filter
 * company/department dilakukan lewat whereHas ke relasi employee.
 *
 * Sengaja TIDAK di-paginate di level query: summary (total per kategori/
 * employee/department/bulan) harus dihitung dari SELURUH data yang lolos
 * filter, bukan cuma 1 halaman -- kalau di-paginate duluan, angka summary
 * jadi salah (cuma agregat 1 page). Pagination/virtualization untuk
 * tabel detail dilakukan di frontend.
 */
class FinanceSpendingReportService
{
    /**
     * @param  array{company_id?: int, date_from?: string, date_to?: string, employee_id?: int, department_id?: int, category?: string, source_type?: string}  $filters
     */
    public function spending(array $filters): Collection
    {
        return collect()
            ->merge($this->expenseClaimRows($filters))
            ->merge($this->cashAdvanceRows($filters))
            ->merge($this->reimbursementRows($filters))
            ->sortByDesc('transaction_date')
            ->values();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows  hasil ->toArray() dari spending()
     */
    public function summary(array $rows): array
    {
        $collection = collect($rows);

        return [
            'total_amount' => $this->sumAmount($collection),
            'total_transactions' => $collection->count(),
            'by_category' => $collection
                ->groupBy(fn (array $row) => $row['category'] ?? 'Tanpa Kategori')
                ->map(fn (Collection $group, string $category) => [
                    'category' => $category,
                    'total_amount' => $this->sumAmount($group),
                    'total_transactions' => $group->count(),
                ])
                ->values(),
            'by_employee' => $collection
                ->groupBy('employee_id')
                ->map(fn (Collection $group) => [
                    'employee_id' => $group->first()['employee_id'],
                    'employee_name' => $group->first()['employee_name'],
                    'total_amount' => $this->sumAmount($group),
                    'total_transactions' => $group->count(),
                ])
                ->values(),
            'by_department' => $collection
                ->groupBy(fn (array $row) => $row['department_id'] ?? 'none')
                ->map(fn (Collection $group) => [
                    'department_id' => $group->first()['department_id'],
                    'department_name' => $group->first()['department_name'] ?? 'Tanpa Departemen',
                    'total_amount' => $this->sumAmount($group),
                    'total_transactions' => $group->count(),
                ])
                ->values(),
            'by_month' => $collection
                ->groupBy(fn (array $row) => substr((string) $row['transaction_date'], 0, 7))
                ->map(fn (Collection $group, string $month) => [
                    'month' => $month,
                    'total_amount' => $this->sumAmount($group),
                    'total_transactions' => $group->count(),
                ])
                ->sortKeys()
                ->values(),
        ];
    }

    private function sumAmount(Collection $rows): string
    {
        return $rows->reduce(
            fn (string $carry, array $row) => ReportMath::add($carry, (string) $row['amount']),
            '0.00',
        );
    }

    private function expenseClaimRows(array $filters): Collection
    {
        if (! empty($filters['source_type']) && $filters['source_type'] !== 'expense_claim') {
            return collect();
        }

        $query = ExpenseClaim::query()
            ->with(['employee.department', 'category'])
            ->whereHas('employee', function ($q) use ($filters) {
                $this->applyCompanyDepartmentFilter($q, $filters);
            });

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('expense_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('expense_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['category'])) {
            $query->whereHas('category', fn ($q) => $q->where('name', $filters['category']));
        }

        return $query->get()->map(fn (ExpenseClaim $claim) => [
            'source_type' => 'expense_claim',
            'source_id' => $claim->id,
            'employee_id' => $claim->employee_id,
            'employee_name' => $this->employeeName($claim->employee),
            'department_id' => $claim->employee?->department_id,
            'department_name' => $claim->employee?->department?->name,
            'category' => $claim->category?->name,
            'amount' => (string) $claim->amount,
            'status' => $claim->status->value,
            'transaction_date' => $claim->expense_date?->toDateString(),
        ]);
    }

    private function cashAdvanceRows(array $filters): Collection
    {
        if (! empty($filters['source_type']) && $filters['source_type'] !== 'cash_advance') {
            return collect();
        }

        $query = CashAdvanceRequestItem::query()
            ->with(['category', 'request.employee.department']);

        $query->whereHas('request', function ($q) use ($filters) {
            if (! empty($filters['employee_id'])) {
                $q->where('employee_id', $filters['employee_id']);
            }

            if (! empty($filters['date_from'])) {
                $q->whereDate('date_of_use', '>=', $filters['date_from']);
            }

            if (! empty($filters['date_to'])) {
                $q->whereDate('date_of_use', '<=', $filters['date_to']);
            }

            $q->whereHas('employee', function ($eq) use ($filters) {
                $this->applyCompanyDepartmentFilter($eq, $filters);
            });
        });

        if (! empty($filters['category'])) {
            $query->whereHas('category', fn ($q) => $q->where('name', $filters['category']));
        }

        return $query->get()->map(function (CashAdvanceRequestItem $item) {
            $request = $item->request;

            return [
                'source_type' => 'cash_advance',
                'source_id' => $request->id,
                'employee_id' => $request->employee_id,
                'employee_name' => $this->employeeName($request->employee),
                'department_id' => $request->employee?->department_id,
                'department_name' => $request->employee?->department?->name,
                'category' => $item->category?->name,
                'amount' => (string) $item->amount,
                'status' => $request->status->value,
                'transaction_date' => $request->date_of_use?->toDateString(),
            ];
        });
    }

    private function reimbursementRows(array $filters): Collection
    {
        if (! empty($filters['source_type']) && $filters['source_type'] !== 'reimbursement') {
            return collect();
        }

        $query = ReimbursementRequestItem::query()
            ->with(['benefit', 'request.employee.department']);

        $query->whereHas('request', function ($q) use ($filters) {
            if (! empty($filters['employee_id'])) {
                $q->where('employee_id', $filters['employee_id']);
            }

            if (! empty($filters['date_from'])) {
                $q->whereDate('transaction_date', '>=', $filters['date_from']);
            }

            if (! empty($filters['date_to'])) {
                $q->whereDate('transaction_date', '<=', $filters['date_to']);
            }

            $q->whereHas('employee', function ($eq) use ($filters) {
                $this->applyCompanyDepartmentFilter($eq, $filters);
            });
        });

        if (! empty($filters['category'])) {
            $query->whereHas('benefit', fn ($q) => $q->where('name', $filters['category']));
        }

        return $query->get()->map(function (ReimbursementRequestItem $item) {
            $request = $item->request;

            return [
                'source_type' => 'reimbursement',
                'source_id' => $request->id,
                'employee_id' => $request->employee_id,
                'employee_name' => $this->employeeName($request->employee),
                'department_id' => $request->employee?->department_id,
                'department_name' => $request->employee?->department?->name,
                'category' => $item->benefit?->name,
                'amount' => (string) $item->amount,
                'status' => $request->status->value,
                'transaction_date' => $request->transaction_date?->toDateString(),
            ];
        });
    }

    private function applyCompanyDepartmentFilter($query, array $filters): void
    {
        if (! empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }
    }

    private function employeeName(?object $employee): ?string
    {
        if (! $employee) {
            return null;
        }

        return trim($employee->first_name.' '.($employee->last_name ?? ''));
    }
}