<?php

namespace App\Modules\Report\Services;

use App\Modules\CashAdvance\Enums\CashAdvanceRequestStatus;
use App\Modules\CashAdvance\Models\CashAdvanceRequest;
use App\Modules\Expense\Enums\ExpenseClaimStatus;
use App\Modules\Expense\Models\ExpenseClaim;
use App\Modules\Loan\Enums\LoanStatus;
use App\Modules\Loan\Models\Loan;
use App\Modules\Reimbursement\Enums\ReimbursementRequestStatus;
use App\Modules\Reimbursement\Models\ReimbursementRequest;
use App\Modules\Report\Support\ReportMath;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only. Definisi "outstanding" per source (pakai status/field existing
 * masing-masing domain, TIDAK ada field baru):
 *
 * - Cash Advance: status need_settlement ATAU settlement_on_review (sudah
 *   disbursed, belum Completed). Uang sudah keluar penuh saat disbursement
 *   -- disbursement itu SENDIRI yang menciptakan liability, jadi tidak ada
 *   "paid_amount" parsial di sini (null), outstanding = total_amount penuh
 *   sampai settlement approved (status jadi Completed).
 * - Loan: status Active. Pakai Loan::outstandingPrincipal() yang SUDAH ADA
 *   di model (bukan hitungan baru) -- itu principal dikurangi yang sudah
 *   Paid dari installments. paid_amount = principal - outstandingPrincipal.
 * - Reimbursement: status Approved DAN disbursed_at masih null (approved
 *   tapi belum dicairkan).
 * - Expense Claim: status Approved DAN paid_at masih null (approved tapi
 *   belum dibayar).
 */
class FinanceOutstandingReportService
{
    /**
     * @param  array{company_id?: int, date_from?: string, date_to?: string, employee_id?: int, department_id?: int, source?: string, status?: string}  $filters
     */
    public function outstanding(array $filters): Collection
    {
        return collect()
            ->merge($this->cashAdvanceRows($filters))
            ->merge($this->loanRows($filters))
            ->merge($this->reimbursementRows($filters))
            ->merge($this->expenseClaimRows($filters))
            ->sortByDesc('aging_days')
            ->values();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows  hasil ->toArray() dari outstanding()
     */
    public function summary(array $rows): array
    {
        $collection = collect($rows);

        return [
            'total_outstanding' => $this->sumOutstanding($collection),
            'total_items' => $collection->count(),
            'by_source' => $collection
                ->groupBy('source')
                ->map(fn (Collection $group, string $source) => [
                    'source' => $source,
                    'total_outstanding' => $this->sumOutstanding($group),
                    'total_items' => $group->count(),
                ])
                ->values(),
            'by_department' => $collection
                ->groupBy(fn (array $row) => $row['department_id'] ?? 'none')
                ->map(fn (Collection $group) => [
                    'department_id' => $group->first()['department_id'],
                    'department_name' => $group->first()['department_name'] ?? 'Tanpa Departemen',
                    'total_outstanding' => $this->sumOutstanding($group),
                    'total_items' => $group->count(),
                ])
                ->values(),
        ];
    }

    private function sumOutstanding(Collection $rows): string
    {
        return $rows->reduce(
            fn (string $carry, array $row) => ReportMath::add($carry, (string) $row['outstanding_amount']),
            '0.00',
        );
    }

    private function cashAdvanceRows(array $filters): Collection
    {
        if (! empty($filters['source']) && $filters['source'] !== 'cash_advance') {
            return collect();
        }

        if (! empty($filters['status']) && ! in_array($filters['status'], [
            CashAdvanceRequestStatus::NeedSettlement->value,
            CashAdvanceRequestStatus::SettlementOnReview->value,
        ], true)) {
            return collect();
        }

        $query = CashAdvanceRequest::query()
            ->with(['employee.department'])
            ->whereIn('status', [
                CashAdvanceRequestStatus::NeedSettlement->value,
                CashAdvanceRequestStatus::SettlementOnReview->value,
            ]);

        $this->applyCommonFilters($query, $filters, dateColumn: 'disbursed_at');

        return $query->get()->map(fn (CashAdvanceRequest $request) => $this->row(
            source: 'cash_advance',
            sourceId: $request->id,
            employee: $request->employee,
            reference: 'CA-'.$request->id.' ('.$request->purpose.')',
            originalAmount: (string) $request->total_amount,
            paidAmount: null,
            outstandingAmount: (string) $request->total_amount,
            status: $request->status->value,
            relevantDate: $request->disbursed_at,
        ));
    }

    private function loanRows(array $filters): Collection
    {
        if (! empty($filters['source']) && $filters['source'] !== 'loan') {
            return collect();
        }

        if (! empty($filters['status']) && $filters['status'] !== LoanStatus::Active->value) {
            return collect();
        }

        $query = Loan::query()
            ->with(['employee.department', 'installments'])
            ->where('status', LoanStatus::Active->value);

        $this->applyCommonFilters($query, $filters, dateColumn: 'disbursed_at');

        return $query->get()
            ->map(function (Loan $loan) {
                $outstanding = $loan->outstandingPrincipal();

                return [$loan, $outstanding];
            })
            // Loan yang principal-nya sudah lunas semua (outstanding 0) tapi
            // status belum sempat dipindah Payroll ke Completed/Settled --
            // bukan liability lagi, jangan dianggap outstanding.
            ->filter(fn (array $pair) => $pair[1] !== '0.00')
            ->map(fn (array $pair) => $this->row(
                source: 'loan',
                sourceId: $pair[0]->id,
                employee: $pair[0]->employee,
                reference: 'LOAN-'.$pair[0]->id.' ('.$pair[0]->purpose.')',
                originalAmount: (string) $pair[0]->principal,
                paidAmount: ReportMath::sub((string) $pair[0]->principal, $pair[1]),
                outstandingAmount: $pair[1],
                status: $pair[0]->status->value,
                relevantDate: $pair[0]->disbursed_at,
            ))
            ->values();
    }

    private function reimbursementRows(array $filters): Collection
    {
        if (! empty($filters['source']) && $filters['source'] !== 'reimbursement') {
            return collect();
        }

        if (! empty($filters['status']) && $filters['status'] !== ReimbursementRequestStatus::Approved->value) {
            return collect();
        }

        $query = ReimbursementRequest::query()
            ->with(['employee.department'])
            ->where('status', ReimbursementRequestStatus::Approved->value)
            ->whereNull('disbursed_at');

        $this->applyCommonFilters($query, $filters, dateColumn: 'decided_at');

        return $query->get()->map(fn (ReimbursementRequest $request) => $this->row(
            source: 'reimbursement',
            sourceId: $request->id,
            employee: $request->employee,
            reference: 'REIMB-'.$request->id,
            originalAmount: (string) $request->total_amount,
            paidAmount: null,
            outstandingAmount: (string) $request->total_amount,
            status: $request->status->value,
            relevantDate: $request->decided_at,
        ));
    }

    private function expenseClaimRows(array $filters): Collection
    {
        if (! empty($filters['source']) && $filters['source'] !== 'expense_claim') {
            return collect();
        }

        if (! empty($filters['status']) && $filters['status'] !== ExpenseClaimStatus::Approved->value) {
            return collect();
        }

        $query = ExpenseClaim::query()
            ->with(['employee.department'])
            ->where('status', ExpenseClaimStatus::Approved->value)
            ->whereNull('paid_at');

        $this->applyCommonFilters($query, $filters, dateColumn: 'decided_at');

        return $query->get()->map(fn (ExpenseClaim $claim) => $this->row(
            source: 'expense_claim',
            sourceId: $claim->id,
            employee: $claim->employee,
            reference: 'EXP-'.$claim->id,
            originalAmount: (string) $claim->amount,
            paidAmount: null,
            outstandingAmount: (string) $claim->amount,
            status: $claim->status->value,
            relevantDate: $claim->decided_at,
        ));
    }

    private function applyCommonFilters($query, array $filters, string $dateColumn): void
    {
        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate($dateColumn, '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate($dateColumn, '<=', $filters['date_to']);
        }

        $query->whereHas('employee', function ($q) use ($filters) {
            if (! empty($filters['company_id'])) {
                $q->where('company_id', $filters['company_id']);
            }

            if (! empty($filters['department_id'])) {
                $q->where('department_id', $filters['department_id']);
            }
        });
    }

    private function row(
        string $source,
        int $sourceId,
        ?object $employee,
        string $reference,
        string $originalAmount,
        ?string $paidAmount,
        string $outstandingAmount,
        string $status,
        ?Carbon $relevantDate,
    ): array {
        return [
            'source' => $source,
            'source_id' => $sourceId,
            'employee_id' => $employee?->id,
            'employee_name' => $employee ? trim($employee->first_name.' '.($employee->last_name ?? '')) : null,
            'department_id' => $employee?->department_id,
            'department_name' => $employee?->department?->name,
            'reference' => $reference,
            'original_amount' => $originalAmount,
            'paid_amount' => $paidAmount,
            'outstanding_amount' => $outstandingAmount,
            'status' => $status,
            'relevant_date' => $relevantDate?->toDateString(),
            'aging_days' => $relevantDate ? (int) $relevantDate->diffInDays(now()) : null,
        ];
    }
}