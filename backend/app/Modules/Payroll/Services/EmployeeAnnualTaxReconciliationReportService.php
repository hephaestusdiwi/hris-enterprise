<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Payroll\Models\EmployeeAnnualTaxReconciliation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Baca employee_annual_tax_reconciliations — SELALU filter ke revisi payroll
 * run yang CURRENT (payslip->payroll_run_revision_id = payroll_run->current_revision),
 * pola sama persis PayrollReportService/PayrollThrReportService, supaya
 * kalau ada recalculate, cuma reconciliation dari revisi terbaru yang dianggap
 * resmi — record revisi lama TETAP ada di DB (histori/audit trail) tapi tidak
 * ikut ke report/BPA1.
 */
class EmployeeAnnualTaxReconciliationReportService
{
    public function baseQuery(): Builder
    {
        return EmployeeAnnualTaxReconciliation::query()
            ->join('payslips', 'employee_annual_tax_reconciliations.payslip_id', '=', 'payslips.id')
            ->join('payroll_runs', 'employee_annual_tax_reconciliations.payroll_run_id', '=', 'payroll_runs.id')
            ->join('payroll_run_revisions', function ($join) {
                $join->on('payslips.payroll_run_revision_id', '=', 'payroll_run_revisions.id')
                    ->on('payroll_run_revisions.revision_number', '=', 'payroll_runs.current_revision');
            })
            ->join('employees', 'employee_annual_tax_reconciliations.employee_id', '=', 'employees.id');
    }

    /**
     * @param  array{company_id?:int,branch_id?:int,employee_id?:int,tax_year?:int}  $filters
     */
    public function filteredQuery(array $filters): Builder
    {
        return $this->baseQuery()
            ->when($filters['tax_year'] ?? null, fn ($q, $v) => $q->where('employee_annual_tax_reconciliations.tax_year', $v))
            ->when($filters['company_id'] ?? null, fn ($q, $v) => $q->where('payroll_runs.company_id', $v))
            ->when($filters['branch_id'] ?? null, fn ($q, $v) => $q->where('employees.branch_id', $v))
            ->when($filters['employee_id'] ?? null, fn ($q, $v) => $q->where('employee_annual_tax_reconciliations.employee_id', $v))
            ->select([
                'employee_annual_tax_reconciliations.*',
                'employees.employee_number',
                'employees.first_name',
                'employees.last_name',
            ]);
    }

    public function detailAll(array $filters): Collection
    {
        return $this->filteredQuery($filters)->orderBy('employees.first_name')->get();
    }

    /**
     * Satu record resmi (revisi current) buat employee+tahun pajak tertentu —
     * dasar generate BPA1.
     */
    public function findCurrentForEmployeeYear(int $employeeId, int $taxYear): ?EmployeeAnnualTaxReconciliation
    {
        return $this->filteredQuery(['employee_id' => $employeeId, 'tax_year' => $taxYear])->first();
    }
}