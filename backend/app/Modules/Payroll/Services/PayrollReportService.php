<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Payroll\Enums\PayslipLineSource;
use App\Modules\Payroll\Enums\PayslipLineType;
use App\Modules\Payroll\Models\Payslip;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Salary Reports (Payroll Reports STEP 1). Murni baca Payslip/PayslipLine
 * yang sudah dihitung & tersimpan oleh Payroll Calculation Engine — tidak
 * ada kalkulasi ulang di sini sama sekali. Semua angka datang langsung dari
 * kolom Payslip, kecuali Basic Salary & Allowance yang didapat lewat
 * subquery agregat ke PayslipLine (kolom itu tidak ada langsung di Payslip).
 */
class PayrollReportService
{
    /**
     * Base query — di-scope ke revisi AKTIF payroll run masing-masing
     * (bukan seluruh histori revisi), konsisten dengan prinsip immutability/
     * "current revision is the source of truth" yang sudah dipakai di
     * seluruh module Payroll. Kalau payroll run direcalculate lagi setelah
     * report ini dibuat, report otomatis reflect angka revisi terbaru di
     * generate berikutnya — bukan angka revisi lama yang sudah usang.
     *
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
                'payroll_runs.id as payroll_run_id',
                'payroll_runs.period_year',
                'payroll_runs.period_month',
                'payslips.gross_earning',
                'payslips.structural_deduction',
                'payslips.manual_deduction_total',
                'payslips.bpjs_employee_total',
                'payslips.bpjs_employer_total',
                'payslips.tax_amount',
                'payslips.loan_deduction_total',
                'payslips.net_pay',
            ])
            ->selectSub(
                fn ($q) => $q->selectRaw('COALESCE(SUM(amount), 0)')
                    ->from('payslip_lines')
                    ->whereColumn('payslip_lines.payslip_id', 'payslips.id')
                    ->where('payslip_lines.source', PayslipLineSource::SalaryStructure->value)
                    ->where('payslip_lines.type', PayslipLineType::Earning->value),
                'basic_salary'
            )
            ->selectSub(
                fn ($q) => $q->selectRaw('COALESCE(SUM(amount), 0)')
                    ->from('payslip_lines')
                    ->whereColumn('payslip_lines.payslip_id', 'payslips.id')
                    ->where('payslip_lines.source', PayslipLineSource::Allowance->value)
                    ->where('payslip_lines.type', PayslipLineType::Earning->value),
                'allowance_total'
            );
    }

    public function salaryDetail(array $filters): LengthAwarePaginator
    {
        return $this->filteredPayslipsQuery($filters)
            ->orderBy('employees.first_name')
            ->paginate(20);
    }

    /**
     * Dataset penuh (bukan paginated) — dipakai baik oleh endpoint summary
     * (buat di-SUM) maupun oleh export (Excel/PDF butuh seluruh baris, bukan
     * 1 halaman).
     */
    public function salaryDetailAll(array $filters): Collection
    {
        return $this->filteredPayslipsQuery($filters)->orderBy('employees.first_name')->get();
    }

    /**
     * Recapitulation — total dari dataset yang SAMA PERSIS dengan Detail
     * (base query di-share), jadi total di Summary dijamin konsisten sama
     * SUM manual dari Detail.
     */
    public function salarySummary(array $filters): array
    {
        $rows = $this->salaryDetailAll($filters);

        return [
            'employee_count' => $rows->count(),
            'basic_salary' => (string) $rows->sum('basic_salary'),
            'allowance_total' => (string) $rows->sum('allowance_total'),
            'gross_earning' => (string) $rows->sum('gross_earning'),
            'structural_deduction' => (string) $rows->sum('structural_deduction'),
            'manual_deduction_total' => (string) $rows->sum('manual_deduction_total'),
            'bpjs_employee_total' => (string) $rows->sum('bpjs_employee_total'),
            'bpjs_employer_total' => (string) $rows->sum('bpjs_employer_total'),
            'tax_amount' => (string) $rows->sum('tax_amount'),
            'loan_deduction_total' => (string) $rows->sum('loan_deduction_total'),
            'net_pay' => (string) $rows->sum('net_pay'),
        ];
    }
}
