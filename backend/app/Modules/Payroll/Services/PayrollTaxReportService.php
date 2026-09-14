<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Payroll\Models\Payslip;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Tax Reports (Payroll Reports STEP 3). Murni baca Payslip.tax_amount yang
 * sudah dihitung TaxCalculationEngine dan tersimpan — tidak ada kalkulasi
 * ulang PPh21 di sini. tax_amount sudah final per payslip (termasuk
 * penyesuaian rekonsiliasi tahunan Desember/resign kalau ada — itu sudah
 * ter-fold jadi satu angka oleh PayrollCalculationEngine, dikonfirmasi dari
 * source, bukan diasumsikan).
 *
 * Yearly tax summary detail (breakdown PTKP/PKP) SENGAJA tidak dikerjakan di
 * sini — itu butuh manggil TaxCalculationEngine::calculateAnnualReconciliation()
 * langsung (bukan baca dari data tersimpan, karena breakdown detailnya
 * memang tidak dipersist), dan sudah di-defer sesuai keputusan sebelumnya.
 */
class PayrollTaxReportService
{
    /**
     * @param  array{company_id?:int,branch_id?:int,department_id?:int,employee_id?:int,period_year:int,period_month:int}  $filters
     */
    public function filteredPayslipsQuery(array $filters): Builder
    {
        return Payslip::query()
            ->join('payroll_runs', 'payslips.payroll_run_id', '=', 'payroll_runs.id')
            ->join('payroll_run_revisions', function ($join) {
                $join->on('payslips.payroll_run_revision_id', '=', 'payroll_run_revisions.id')
                    ->on('payroll_run_revisions.revision_number', '=', 'payroll_runs.current_revision');
            })
            ->join('employees', 'payslips.employee_id', '=', 'employees.id')
            ->where('payroll_runs.period_year', $filters['period_year'])
            ->where('payroll_runs.period_month', $filters['period_month'])
            ->when($filters['company_id'] ?? null, fn ($q, $v) => $q->where('payroll_runs.company_id', $v))
            ->when($filters['branch_id'] ?? null, fn ($q, $v) => $q->where('employees.branch_id', $v))
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->where('employees.department_id', $v))
            ->when($filters['employee_id'] ?? null, fn ($q, $v) => $q->where('payslips.employee_id', $v))
            ->select([
                'payslips.id',
                'payslips.employee_id',
                'employees.employee_number',
                'employees.first_name',
                'employees.last_name',
                'payroll_runs.period_year',
                'payroll_runs.period_month',
                'payslips.gross_earning',
                'payslips.tax_amount',
            ])
            // Penanda apakah bulan ini termasuk rekonsiliasi tahunan — murni
            // baca label yang sudah ada, bukan hitung ulang.
            ->selectSub(
                fn ($q) => $q->selectRaw("CASE WHEN COUNT(*) > 0 THEN true ELSE false END")
                    ->from('payslip_lines')
                    ->whereColumn('payslip_lines.payslip_id', 'payslips.id')
                    ->where('payslip_lines.label', 'PPh 21 (Rekonsiliasi Tahunan)'),
                'is_annual_reconciliation'
            );
    }

    public function taxDetail(array $filters): LengthAwarePaginator
    {
        return $this->filteredPayslipsQuery($filters)->orderBy('employees.first_name')->paginate(20);
    }

    public function taxDetailAll(array $filters): Collection
    {
        return $this->filteredPayslipsQuery($filters)->orderBy('employees.first_name')->get();
    }

    public function taxSummary(array $filters): array
    {
        $rows = $this->taxDetailAll($filters);

        return [
            'employee_count' => $rows->count(),
            'gross_earning' => (string) $rows->sum('gross_earning'),
            'tax_amount' => (string) $rows->sum('tax_amount'),
        ];
    }
}
