<?php

namespace App\Modules\Report\Services;

use App\Models\User;
use App\Modules\Attendance\Services\ApprovalStepApproverResolver;
use App\Modules\CashAdvance\Enums\CashAdvanceApprovalRequestStatus;
use App\Modules\CashAdvance\Models\CashAdvanceApprovalRequest;
use App\Modules\Expense\Enums\ExpenseClaimApprovalRequestStatus;
use App\Modules\Expense\Models\ExpenseClaimApprovalRequest;
use App\Modules\Loan\Enums\LoanApprovalRequestStatus;
use App\Modules\Loan\Models\LoanApprovalRequest;
use App\Modules\Reimbursement\Enums\ReimbursementApprovalRequestStatus;
use App\Modules\Reimbursement\Models\ReimbursementApprovalRequest;
use Illuminate\Support\Collection;

/**
 * Read-only. 4 domain (Loan, Reimbursement, CashAdvance, Expense Claim)
 * PERSIS sama strukturnya: {Domain}ApprovalRequest punya employee_id,
 * status (enum Pending/Approved/Rejected -- sama di semua 4),
 * current_step_sequence, requested_at, decided_at, dan stepDecisions()
 * hasMany {Domain}ApprovalStepDecision (approval_step_id, sequence, status).
 * Payroll SENGAJA TIDAK dimasukkan (instruksi eksplisit: jangan sentuh
 * Payroll) -- juga secara desain PayrollApprovalService subject-nya bukan
 * Employee, tidak cocok dengan bentuk report per-employee ini.
 *
 * "Approver" dihitung pakai ApprovalStepApproverResolver YANG SUDAH ADA
 * (dipakai juga oleh CashAdvanceApprovalController dkk untuk cek eligibility
 * saat decide()) -- TIDAK ada approval engine baru, cuma dipakai buat
 * ditampilkan siapa yang eligible mutusin step saat ini.
 */
class FinanceApprovalAgingReportService
{
    public function __construct(
        private ApprovalStepApproverResolver $resolver,
    ) {
    }

    /**
     * @param  array{company_id?: int, date_from?: string, date_to?: string, employee_id?: int, department_id?: int, source?: string, status?: string, approver_user_id?: int, aging_min?: int, aging_max?: int}  $filters
     */
    public function aging(array $filters): Collection
    {
        $rows = collect()
            ->merge($this->loanRows($filters))
            ->merge($this->reimbursementRows($filters))
            ->merge($this->cashAdvanceRows($filters))
            ->merge($this->expenseClaimRows($filters));

        $rows = $this->resolveApproverNames($rows);

        if (! empty($filters['approver_user_id'])) {
            $rows = $rows->filter(fn (array $row) => in_array((int) $filters['approver_user_id'], $row['approver_user_ids'], true));
        }

        if (isset($filters['aging_min'])) {
            $rows = $rows->filter(fn (array $row) => $row['aging_days'] >= (int) $filters['aging_min']);
        }

        if (isset($filters['aging_max'])) {
            $rows = $rows->filter(fn (array $row) => $row['aging_days'] <= (int) $filters['aging_max']);
        }

        return $rows
            ->map(fn (array $row) => collect($row)->except('approver_user_ids')->all())
            ->sortByDesc('aging_days')
            ->values();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows  hasil ->toArray() dari aging()
     */
    public function summary(array $rows): array
    {
        $collection = collect($rows);

        return [
            'total_items' => $collection->count(),
            'by_source' => $collection
                ->groupBy('source')
                ->map(fn (Collection $group, string $source) => [
                    'source' => $source,
                    'total_items' => $group->count(),
                ])
                ->values(),
            'by_department' => $collection
                ->groupBy(fn (array $row) => $row['department_id'] ?? 'none')
                ->map(fn (Collection $group) => [
                    'department_id' => $group->first()['department_id'],
                    'department_name' => $group->first()['department_name'] ?? 'Tanpa Departemen',
                    'total_items' => $group->count(),
                ])
                ->values(),
            'by_aging_bucket' => collect([
                '0-3 hari' => $collection->whereBetween('aging_days', [0, 3])->count(),
                '4-7 hari' => $collection->whereBetween('aging_days', [4, 7])->count(),
                '8-14 hari' => $collection->whereBetween('aging_days', [8, 14])->count(),
                '15-30 hari' => $collection->whereBetween('aging_days', [15, 30])->count(),
                '> 30 hari' => $collection->filter(fn (array $row) => $row['aging_days'] > 30)->count(),
            ])->map(fn (int $count, string $bucket) => ['bucket' => $bucket, 'total_items' => $count])->values(),
        ];
    }

    private function loanRows(array $filters): Collection
    {
        if (! empty($filters['source']) && $filters['source'] !== 'loan') {
            return collect();
        }

        $query = LoanApprovalRequest::query()
            ->where('status', LoanApprovalRequestStatus::Pending->value)
            ->with(['employee.department', 'employee.manager', 'stepDecisions.approvalStep.approverEmployee', 'stepDecisions.approvalStep.approverRole']);

        $this->applyCommonFilters($query, $filters);

        return $query->get()->map(fn (LoanApprovalRequest $request) => $this->row(
            source: 'loan',
            sourceId: $request->loan_id,
            reference: 'LOAN-'.$request->loan_id,
            request: $request,
        ));
    }

    private function reimbursementRows(array $filters): Collection
    {
        if (! empty($filters['source']) && $filters['source'] !== 'reimbursement') {
            return collect();
        }

        $query = ReimbursementApprovalRequest::query()
            ->where('status', ReimbursementApprovalRequestStatus::Pending->value)
            ->with(['employee.department', 'employee.manager', 'stepDecisions.approvalStep.approverEmployee', 'stepDecisions.approvalStep.approverRole']);

        $this->applyCommonFilters($query, $filters);

        return $query->get()->map(fn (ReimbursementApprovalRequest $request) => $this->row(
            source: 'reimbursement',
            sourceId: $request->reimbursement_request_id,
            reference: 'REIMB-'.$request->reimbursement_request_id,
            request: $request,
        ));
    }

    private function cashAdvanceRows(array $filters): Collection
    {
        if (! empty($filters['source']) && $filters['source'] !== 'cash_advance') {
            return collect();
        }

        $query = CashAdvanceApprovalRequest::query()
            ->where('status', CashAdvanceApprovalRequestStatus::Pending->value)
            ->with(['employee.department', 'employee.manager', 'stepDecisions.approvalStep.approverEmployee', 'stepDecisions.approvalStep.approverRole']);

        $this->applyCommonFilters($query, $filters);

        return $query->get()->map(fn (CashAdvanceApprovalRequest $request) => $this->row(
            source: 'cash_advance',
            sourceId: $request->cash_advance_request_id,
            reference: 'CA-'.$request->cash_advance_request_id,
            request: $request,
        ));
    }

    private function expenseClaimRows(array $filters): Collection
    {
        if (! empty($filters['source']) && $filters['source'] !== 'expense_claim') {
            return collect();
        }

        $query = ExpenseClaimApprovalRequest::query()
            ->where('status', ExpenseClaimApprovalRequestStatus::Pending->value)
            ->with(['employee.department', 'employee.manager', 'stepDecisions.approvalStep.approverEmployee', 'stepDecisions.approvalStep.approverRole']);

        $this->applyCommonFilters($query, $filters);

        return $query->get()->map(fn (ExpenseClaimApprovalRequest $request) => $this->row(
            source: 'expense_claim',
            sourceId: $request->expense_claim_id,
            reference: 'EXP-'.$request->expense_claim_id,
            request: $request,
        ));
    }

    private function applyCommonFilters($query, array $filters): void
    {
        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('requested_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('requested_at', '<=', $filters['date_to']);
        }

        // status difilter di caller (aging() cuma pernah query Pending);
        // kalau user filter status selain 'pending', hasilnya kosong --
        // itu benar, karena report ini emang cuma soal yang masih pending.
        if (! empty($filters['status']) && $filters['status'] !== 'pending') {
            $query->whereRaw('1 = 0');
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

    private function row(string $source, ?int $sourceId, string $reference, $request): array
    {
        $currentDecision = $request->stepDecisions->firstWhere('sequence', $request->current_step_sequence);
        $approvalStep = $currentDecision?->approvalStep;

        return [
            'source' => $source,
            'source_id' => $sourceId,
            'employee_id' => $request->employee_id,
            'employee_name' => $request->employee
                ? trim($request->employee->first_name.' '.($request->employee->last_name ?? ''))
                : null,
            'department_id' => $request->employee?->department_id,
            'department_name' => $request->employee?->department?->name,
            'reference' => $reference,
            'current_step_name' => $approvalStep?->name ?? ('Step '.$request->current_step_sequence),
            'approver_user_ids' => $approvalStep
                ? $this->resolver->resolveApproverUserIds($approvalStep, $request->employee)
                : [],
            'status' => $request->status->value,
            'requested_at' => $request->requested_at?->toDateTimeString(),
            'aging_days' => $request->requested_at ? (int) $request->requested_at->diffInDays(now()) : 0,
        ];
    }

    private function resolveApproverNames(Collection $rows): Collection
    {
        $allUserIds = $rows->flatMap(fn (array $row) => $row['approver_user_ids'])->unique()->values();

        $namesById = $allUserIds->isEmpty()
            ? collect()
            : User::whereIn('id', $allUserIds)->pluck('name', 'id');

        return $rows->map(function (array $row) use ($namesById) {
            $names = collect($row['approver_user_ids'])->map(fn (int $id) => $namesById->get($id))->filter();

            $row['approver'] = $names->isNotEmpty()
                ? $names->implode(', ')
                : 'Tidak ada approver eligible (flow salah konfigurasi)';

            return $row;
        });
    }
}