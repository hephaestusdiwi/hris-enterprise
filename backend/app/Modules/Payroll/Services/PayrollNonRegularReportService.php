<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Payroll\Models\EmployeeNonRegularInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Non-Regular Payroll Report — dibaca per EmployeeNonRegularInput (bukan per
 * Payslip) karena granularitas yang diminta section I adalah per component
 * per employee ("employee, component, type, amount, taxable, deduction,
 * status"), bukan per payslip agregat. Kolom "net" diambil dari net_pay
 * payslip agregat (kalau run-nya sudah diproses) lewat left join — tidak
 * ada kalkulasi ulang, murni baca data yang sudah tersimpan.
 */
class PayrollNonRegularReportService
{
    /**
     * @param  array{company_id?:int,branch_id?:int,employee_id?:int,payroll_run_id?:int,period_year?:int,period_month?:int,non_regular_payroll_component_id?:int,status?:string}  $filters
     */
    public function filteredQuery(array $filters): Builder
    {
        return EmployeeNonRegularInput::query()
            ->join('employees', 'employee_non_regular_inputs.employee_id', '=', 'employees.id')
            ->join('non_regular_payroll_components', 'employee_non_regular_inputs.non_regular_payroll_component_id', '=', 'non_regular_payroll_components.id')
            ->leftJoin('payslips', function ($join) {
                $join->on('payslips.employee_id', '=', 'employee_non_regular_inputs.employee_id')
                    ->on('payslips.payroll_run_id', '=', 'employee_non_regular_inputs.payroll_run_id');
            })
            ->when($filters['payroll_run_id'] ?? null, fn ($q, $v) => $q->where('employee_non_regular_inputs.payroll_run_id', $v))
            ->when($filters['period_year'] ?? null, fn ($q, $v) => $q->where('employee_non_regular_inputs.payroll_period_year', $v))
            ->when($filters['period_month'] ?? null, fn ($q, $v) => $q->where('employee_non_regular_inputs.payroll_period_month', $v))
            ->when($filters['company_id'] ?? null, fn ($q, $v) => $q->where('employees.company_id', $v))
            ->when($filters['branch_id'] ?? null, fn ($q, $v) => $q->where('employees.branch_id', $v))
            ->when($filters['employee_id'] ?? null, fn ($q, $v) => $q->where('employee_non_regular_inputs.employee_id', $v))
            ->when($filters['non_regular_payroll_component_id'] ?? null, fn ($q, $v) => $q->where('employee_non_regular_inputs.non_regular_payroll_component_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('employee_non_regular_inputs.status', $v))
            ->select([
                'employee_non_regular_inputs.id',
                'employee_non_regular_inputs.employee_id',
                'employees.employee_number',
                'employees.first_name',
                'employees.last_name',
                'non_regular_payroll_components.name as component_name',
                'non_regular_payroll_components.category',
                'employee_non_regular_inputs.amount',
                'employee_non_regular_inputs.is_addition',
                'non_regular_payroll_components.is_taxable',
                'employee_non_regular_inputs.payroll_period_year',
                'employee_non_regular_inputs.payroll_period_month',
                'employee_non_regular_inputs.payroll_run_id',
                'employee_non_regular_inputs.status',
                'payslips.net_pay',
            ]);
    }

    public function detailAll(array $filters): Collection
    {
        return $this->filteredQuery($filters)->orderBy('employees.first_name')->get();
    }
}