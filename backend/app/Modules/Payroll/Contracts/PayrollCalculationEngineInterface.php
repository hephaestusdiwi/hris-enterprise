<?php

namespace App\Modules\Payroll\Contracts;

use App\Modules\Payroll\DataTransferObjects\EmployeePayslipDraft;
use App\Modules\Payroll\Models\PayrollRun;

interface PayrollCalculationEngineInterface
{
    /**
     * Hitung draft payslip SEMUA participant di run ini — murni kalkulasi
     * (baca engine lain: EmployeeSalaryResolver, BpjsCalculationEngine,
     * TaxCalculationEngine, AttendanceReportService), TIDAK menulis apa pun
     * ke DB dan TIDAK menandai EmployeeAllowance/Deduction/LoanInstallment
     * sebagai consumed — itu baru terjadi di PayrollRunService::lock().
     *
     * @return array<int, EmployeePayslipDraft> keyed by employee_id
     */
    public function calculateDraftsForRun(PayrollRun $payrollRun): array;
}