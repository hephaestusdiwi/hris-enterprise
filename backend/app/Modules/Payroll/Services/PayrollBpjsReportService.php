<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Payroll\Models\Payslip;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * BPJS Reports (Payroll Reports STEP 2). Murni baca Payslip/PayslipLine yang
 * sudah dihitung & tersimpan — tidak ada kalkulasi ulang BPJS di sini.
 *
 * Breakdown per program (Kesehatan/JHT/JKK/JKM) didapat via exact-match
 * label PayslipLine, karena PayrollCalculationEngine menulis label BPJS
 * dengan format deterministik: strtoupper($programKey).' (Karyawan)'/' (Company)'
 * (dikonfirmasi dari source, bukan asumsi). JP sengaja tidak ada kolomnya —
 * program itu belum diaktifkan di BpjsCalculationEngine (case-nya masih
 * di-comment di BpjsProgram enum), jadi tidak ada PayslipLine berlabel JP
 * sama sekali. Report ini tidak membuat angka JP palsu — kalau nanti JP
 * diaktifkan di engine, kolom JP di report ini akan otomatis terisi tanpa
 * perlu ubah kode report sama sekali (query-nya generic per-program).
 */
class PayrollBpjsReportService
{
    private const PROGRAMS = ['KESEHATAN', 'JHT', 'JKK', 'JKM'];

    /**
     * @param  array{company_id?:int,branch_id?:int,period_year:int,period_month:int}  $filters
     */
    public function filteredPayslipsQuery(array $filters): Builder
    {
        $query = Payslip::query()
            ->join('payroll_runs', 'payslips.payroll_run_id', '=', 'payroll_runs.id')
            ->join('payroll_run_revisions', function ($join) {
                $join->on('payslips.payroll_run_revision_id', '=', 'payroll_run_revisions.id')
                    ->on('payroll_run_revisions.revision_number', '=', 'payroll_runs.current_revision');
            })
            ->join('employees', 'payslips.employee_id', '=', 'employees.id')
            ->leftJoin('employee_bpjs_participations', 'employee_bpjs_participations.employee_id', '=', 'employees.id')
            ->where('payroll_runs.period_year', $filters['period_year'])
            ->where('payroll_runs.period_month', $filters['period_month'])
            ->when($filters['company_id'] ?? null, fn ($q, $v) => $q->where('payroll_runs.company_id', $v))
            ->when($filters['branch_id'] ?? null, fn ($q, $v) => $q->where('employees.branch_id', $v))
            ->select([
                'payslips.id',
                'payslips.employee_id',
                'employees.employee_number',
                'employees.first_name',
                'employees.last_name',
                'employee_bpjs_participations.bpjs_registration_npp_number as npp_number',
                'payslips.bpjs_employee_total',
                'payslips.bpjs_employer_total',
            ]);

        foreach (self::PROGRAMS as $program) {
            $query->selectSub(
                fn ($q) => $q->selectRaw('COALESCE(SUM(amount), 0.00)')
                    ->from('payslip_lines')
                    ->whereColumn('payslip_lines.payslip_id', 'payslips.id')
                    ->where('payslip_lines.label', "{$program} (Karyawan)"),
                strtolower($program).'_employee'
            )->selectSub(
                fn ($q) => $q->selectRaw('COALESCE(SUM(amount), 0.00)')
                    ->from('payslip_lines')
                    ->whereColumn('payslip_lines.payslip_id', 'payslips.id')
                    ->where('payslip_lines.label', "{$program} (Company)"),
                strtolower($program).'_employer'
            );
        }

        return $query;
    }

    public function bpjsDetail(array $filters): LengthAwarePaginator
    {
        return $this->filteredPayslipsQuery($filters)->orderBy('employees.first_name')->paginate(20);
    }

    public function bpjsDetailAll(array $filters): Collection
    {
        return $this->filteredPayslipsQuery($filters)->orderBy('employees.first_name')->get();
    }

    public function bpjsSummary(array $filters): array
    {
        $rows = $this->bpjsDetailAll($filters);

        $summary = [
            'employee_count' => $rows->count(),
            'bpjs_employee_total' => (string) $rows->sum('bpjs_employee_total'),
            'bpjs_employer_total' => (string) $rows->sum('bpjs_employer_total'),
        ];

        foreach (self::PROGRAMS as $program) {
            $key = strtolower($program);
            $summary["{$key}_employee"] = (string) $rows->sum("{$key}_employee");
            $summary["{$key}_employer"] = (string) $rows->sum("{$key}_employer");
        }

        return $summary;
    }
}
