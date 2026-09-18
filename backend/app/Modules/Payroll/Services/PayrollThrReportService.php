<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Employee\Models\Employee;
use App\Modules\EmployeeSalary\Contracts\EmployeeSalaryResolverInterface;
use App\Modules\Payroll\Enums\PayrollRunType;
use App\Modules\Payroll\Models\Payslip;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * THR Report — baca Payslip milik PayrollRun type=thr (pola query SAMA
 * seperti PayrollReportService::filteredPayslipsQuery, cuma filter type).
 * Dua kolom (service duration & reference/basic salary) TIDAK ada di
 * Payslip/PayslipLine (Payslip THR cuma nyimpen angka SUDAH diprorata) —
 * jadi khusus dua ini diresolve fresh lewat ThrEligibilityService &
 * EmployeeSalaryResolver yang SAMA dipakai kalkulasi, bukan implementasi
 * kedua. Sisanya murni baca kolom Payslip, tidak dihitung ulang.
 */
class PayrollThrReportService
{
    public function __construct(
        private EmployeeSalaryResolverInterface $salaryResolver,
        private ThrEligibilityService $eligibilityService,
    ) {
    }

    /**
     * @param  array{company_id?:int,branch_id?:int,employee_id?:int,payroll_run_id?:int,period_year?:int,period_month?:int}  $filters
     */
    public function filteredQuery(array $filters): Builder
    {
        return Payslip::query()
            ->join('payroll_runs', 'payslips.payroll_run_id', '=', 'payroll_runs.id')
            ->join('payroll_run_revisions', function ($join) {
                $join->on('payslips.payroll_run_revision_id', '=', 'payroll_run_revisions.id')
                    ->on('payroll_run_revisions.revision_number', '=', 'payroll_runs.current_revision');
            })
            ->join('employees', 'payslips.employee_id', '=', 'employees.id')
            ->where('payroll_runs.type', PayrollRunType::Thr->value)
            ->when($filters['payroll_run_id'] ?? null, fn ($q, $v) => $q->where('payroll_runs.id', $v))
            ->when($filters['period_year'] ?? null, fn ($q, $v) => $q->where('payroll_runs.period_year', $v))
            ->when($filters['period_month'] ?? null, fn ($q, $v) => $q->where('payroll_runs.period_month', $v))
            ->when($filters['company_id'] ?? null, fn ($q, $v) => $q->where('payroll_runs.company_id', $v))
            ->when($filters['branch_id'] ?? null, fn ($q, $v) => $q->where('employees.branch_id', $v))
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
                'payroll_runs.status',
                'payroll_runs.payment_date',
                'payslips.gross_earning as thr_amount',
                'payslips.bpjs_employee_total as deduction',
                'payslips.tax_amount',
                'payslips.net_pay',
            ]);
    }

    public function detailAll(array $filters): Collection
    {
        $rows = $this->filteredQuery($filters)->orderBy('employees.first_name')->get();

        $employees = Employee::whereIn('id', $rows->pluck('employee_id')->unique())->get()->keyBy('id');

        return $rows->map(function ($row) use ($employees) {
            $employee = $employees->get($row->employee_id);
            $referenceDate = $row->payment_date ? Carbon::parse($row->payment_date) : Carbon::createFromDate($row->period_year, $row->period_month, 1)->endOfMonth();

            $basicSalary = '0.00';

            if ($employee) {
                $structuralLines = $this->salaryResolver->resolveComponents($employee, $referenceDate);
                $basicSalary = collect($structuralLines)->first(fn ($l) => $l->component->category?->value === 'basic_salary')?->amount ?? '0.00';
                $row->service_months = $this->eligibilityService->serviceMonths($employee, $referenceDate);
            } else {
                $row->service_months = 0;
            }

            $row->basic_salary = $basicSalary;

            return $row;
        });
    }
}