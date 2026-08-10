<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Attendance\Services\AttendanceReportService;
use App\Modules\Bpjs\Contracts\BpjsCalculationEngineInterface;
use App\Modules\Employee\Models\Employee;
use App\Modules\EmployeeAllowance\Models\EmployeeAllowance;
use App\Modules\EmployeeDeduction\Models\EmployeeDeduction;
use App\Modules\EmployeeSalary\Contracts\EmployeeSalaryResolverInterface;
use App\Modules\EmployeeSalary\DataTransferObjects\ResolvedSalaryLine;
use App\Modules\Loan\Enums\LoanInstallmentStatus;
use App\Modules\Loan\Models\LoanInstallment;
use App\Modules\Payroll\Contracts\PayrollCalculationEngineInterface;
use App\Modules\Payroll\DataTransferObjects\EmployeePayslipDraft;
use App\Modules\Payroll\DataTransferObjects\PayslipLineDraft;
use App\Modules\Payroll\Enums\PayslipLineSource;
use App\Modules\Payroll\Enums\PayslipLineType;
use App\Modules\Payroll\Models\CompanyPayrollAttendanceSetting;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Support\PayrollMath;
use App\Modules\Pph21\Contracts\TaxCalculationEngineInterface;
use Carbon\Carbon;

class PayrollCalculationEngine implements PayrollCalculationEngineInterface
{
    public function __construct(
        private EmployeeSalaryResolverInterface $salaryResolver,
        private BpjsCalculationEngineInterface $bpjsEngine,
        private TaxCalculationEngineInterface $taxEngine,
        private AttendanceReportService $attendanceReportService,
        private PayrollHistoryReader $historyReader,
    ) {
    }

    public function calculateDraftsForRun(PayrollRun $payrollRun): array
    {
        $referenceDate = Carbon::createFromDate($payrollRun->period_year, $payrollRun->period_month, 1)->endOfMonth();
        $periodStart = $referenceDate->copy()->startOfMonth();
        $periodEnd = $payrollRun->cutoff_date ?? $referenceDate->copy()->endOfMonth();

        $employees = $payrollRun->participants;
        $attendanceSetting = CompanyPayrollAttendanceSetting::where('company_id', $payrollRun->company_id)->first();

        $attendanceSummaries = ($attendanceSetting?->enable_attendance_integration ?? false)
            ? collect($this->attendanceReportService->summarize($employees, $periodStart, $periodEnd))->keyBy(fn ($row) => $row['employee']['id'])
            : collect();

        $drafts = [];

        foreach ($employees as $employee) {
            $drafts[$employee->id] = $this->calculateForEmployee(
                $employee,
                $payrollRun,
                $referenceDate,
                $attendanceSetting,
                $attendanceSummaries->get($employee->id),
            );
        }

        return $drafts;
    }

    private function calculateForEmployee(
        Employee $employee,
        PayrollRun $payrollRun,
        Carbon $referenceDate,
        ?CompanyPayrollAttendanceSetting $attendanceSetting,
        ?array $attendanceSummary,
    ): EmployeePayslipDraft {
        $lines = [];

        // 1. Struktur gaji (basic salary + component tetap dari Salary Structure)
        $structuralLines = $this->salaryResolver->resolveComponents($employee, $referenceDate);
        $earningLines = [];

        foreach ($structuralLines as $line) {
            $earningLines[] = $line;
            $lines[] = new PayslipLineDraft(
                type: $line->component->is_addition ? PayslipLineType::Earning : PayslipLineType::Deduction,
                source: PayslipLineSource::SalaryStructure,
                label: $line->component->name,
                amount: $line->amount,
            );
        }

        // 2. Attendance — overtime & late, cuma kalau company aktifin & component-nya dikonfigurasi
        // [Inferensi teknis: formula Kepmenaker 102/2004 disederhanakan, tidak bedain lembur hari libur —
        // audit terbaru sengaja TIDAK merefactor ini lebih jauh, sesuai instruksi]
        if ($attendanceSetting?->enable_attendance_integration && $attendanceSummary) {
            $basicSalaryAmount = collect($structuralLines)->first(fn ($l) => $l->component->category?->value === 'basic_salary')?->amount ?? '0.00';

            if ($attendanceSetting->overtime_salary_component_id && ($attendanceSummary['overtime_minutes'] ?? 0) > 0) {
                $overtimeAmount = $this->calculateOvertimePay($basicSalaryAmount, $attendanceSummary['overtime_minutes'], $attendanceSetting);
                $component = $attendanceSetting->overtimeSalaryComponent;

                if ($component && PayrollMath::add($overtimeAmount, '0') !== '0.00') {
                    $earningLines[] = new ResolvedSalaryLine($component, $overtimeAmount, null, null, 'attendance');
                    $lines[] = new PayslipLineDraft(PayslipLineType::Earning, PayslipLineSource::Attendance, 'Lembur (' . $attendanceSummary['overtime_minutes'] . ' menit)', $overtimeAmount);
                }
            }
        }

        $lateDeductionAmount = '0.00';

        if ($attendanceSetting?->enable_attendance_integration && $attendanceSummary && $attendanceSetting->late_deduction_per_minute) {
            $lateMinutesTotal = ($attendanceSummary['late_days'] ?? 0) > 0 ? $this->estimateLateMinutes($employee, $payrollRun) : 0;

            if ($lateMinutesTotal > 0) {
                $lateDeductionAmount = PayrollMath::mul((string) $lateMinutesTotal, (string) $attendanceSetting->late_deduction_per_minute);
                $lines[] = new PayslipLineDraft(PayslipLineType::Deduction, PayslipLineSource::Attendance, 'Potongan Telat', $lateDeductionAmount);
            }
        }

        // 3. Allowance & Deduction manual (Ready, period ini)
        $readyAllowances = EmployeeAllowance::where('employee_id', $employee->id)
            ->where('status', 'ready')
            ->where('payroll_period_year', $payrollRun->period_year)
            ->where('payroll_period_month', $payrollRun->period_month)
            ->with('salaryComponent')
            ->get();

        foreach ($readyAllowances as $allowance) {
            $earningLines[] = new ResolvedSalaryLine($allowance->salaryComponent, (string) $allowance->amount, null, null, 'allowance');
            $lines[] = new PayslipLineDraft(PayslipLineType::Earning, PayslipLineSource::Allowance, $allowance->salaryComponent->name, (string) $allowance->amount, $allowance->id);
        }

        $readyDeductions = EmployeeDeduction::where('employee_id', $employee->id)
            ->where('status', 'ready')
            ->where('payroll_period_year', $payrollRun->period_year)
            ->where('payroll_period_month', $payrollRun->period_month)
            ->with('salaryComponent')
            ->get();

        $manualDeductionTotal = $lateDeductionAmount;

        foreach ($readyDeductions as $deduction) {
            $manualDeductionTotal = PayrollMath::add($manualDeductionTotal, (string) $deduction->amount);
            $lines[] = new PayslipLineDraft(PayslipLineType::Deduction, PayslipLineSource::Deduction, $deduction->salaryComponent->name, (string) $deduction->amount, $deduction->id);
        }

        // 4. BPJS — pakai gabungan earning lines (structural + attendance + allowance) yg include_in_bpjs_base
        $resolvedBpjsContributions = $this->bpjsEngine->calculateForEmployee($employee, $referenceDate, $earningLines);
        $bpjsEmployeeTotal = '0.00';
        $bpjsEmployerTotal = '0.00';

        foreach ($resolvedBpjsContributions as $programKey => $contribution) {
            $bpjsEmployeeTotal = PayrollMath::add($bpjsEmployeeTotal, $contribution->employeeAmount);
            $bpjsEmployerTotal = PayrollMath::add($bpjsEmployerTotal, $contribution->employerAmount);

            if (PayrollMath::add($contribution->employeeAmount, '0') !== '0.00') {
                $lines[] = new PayslipLineDraft(PayslipLineType::BpjsEmployee, PayslipLineSource::Bpjs, strtoupper($programKey) . ' (Karyawan)', $contribution->employeeAmount);
            }

            if (PayrollMath::add($contribution->employerAmount, '0') !== '0.00') {
                $lines[] = new PayslipLineDraft(PayslipLineType::BpjsEmployer, PayslipLineSource::Bpjs, strtoupper($programKey) . ' (Company)', $contribution->employerAmount);
            }
        }

        // 5. PPh21 — final tax period (Desember ATAU resign di periode ini) pakai
        // rekonsiliasi tahunan, selain itu TER bulanan. Flag ditentukan eksplisit
        // di sini (bukan month===12 doang) lalu "diberitahukan" ke Tax Engine
        // lewat pemilihan method yang dipanggil — Tax Engine sendiri tetap
        // stateless, tidak ada perubahan di app/Modules/Pph21 sama sekali.
        $taxAmount = '0.00';
        $isFinalTaxPeriod = $this->isFinalTaxPeriod($employee, $payrollRun);

        if ($isFinalTaxPeriod) {
            $priorMonths = $this->historyReader->priorMonthsInYear($payrollRun->company_id, $employee->id, $payrollRun->period_year, $payrollRun->period_month);
            $result = $this->taxEngine->calculateAnnualReconciliation($employee, $referenceDate, $priorMonths, $earningLines, $resolvedBpjsContributions);

            if ($result) {
                $taxAmount = $result->finalPeriodAdjustment;
                $lines[] = new PayslipLineDraft(PayslipLineType::Tax, PayslipLineSource::Pph21, 'PPh 21 (Rekonsiliasi Tahunan)', $taxAmount);
            }
        } else {
            $result = $this->taxEngine->calculateMonthly($employee, $referenceDate, $earningLines, $resolvedBpjsContributions);

            if ($result) {
                $taxAmount = $result->takeHomePayDeduction;
                $lines[] = new PayslipLineDraft(PayslipLineType::Tax, PayslipLineSource::Pph21, 'PPh 21', $result->pph21Amount);
            }
        }

        // 6. Loan installment yang jatuh tempo periode ini
        $dueInstallments = LoanInstallment::whereHas('loan', fn ($q) => $q->where('employee_id', $employee->id))
            ->where('status', LoanInstallmentStatus::Scheduled->value)
            ->where('payroll_period_year', $payrollRun->period_year)
            ->where('payroll_period_month', $payrollRun->period_month)
            ->get();

        $loanDeductionTotal = '0.00';

        foreach ($dueInstallments as $installment) {
            $loanDeductionTotal = PayrollMath::add($loanDeductionTotal, (string) $installment->amount);
            $lines[] = new PayslipLineDraft(PayslipLineType::LoanInstallment, PayslipLineSource::Loan, 'Cicilan Loan #' . $installment->installment_number, (string) $installment->amount, $installment->id);
        }

        // 7. Totalisasi
        $grossEarning = '0.00';
        $structuralDeduction = '0.00';

        foreach ($earningLines as $line) {
            if ($line->component->is_addition) {
                $grossEarning = PayrollMath::add($grossEarning, $line->amount ?? '0.00');
            } else {
                $structuralDeduction = PayrollMath::add($structuralDeduction, $line->amount ?? '0.00');
            }
        }

        $netPay = $grossEarning;
        $netPay = PayrollMath::sub($netPay, $structuralDeduction);
        $netPay = PayrollMath::sub($netPay, $manualDeductionTotal);
        $netPay = PayrollMath::sub($netPay, $bpjsEmployeeTotal);
        $netPay = PayrollMath::sub($netPay, $taxAmount);
        $netPay = PayrollMath::sub($netPay, $loanDeductionTotal);

        return new EmployeePayslipDraft(
            employeeId: $employee->id,
            grossEarning: $grossEarning,
            structuralDeduction: $structuralDeduction,
            manualDeductionTotal: $manualDeductionTotal,
            bpjsEmployeeTotal: $bpjsEmployeeTotal,
            bpjsEmployerTotal: $bpjsEmployerTotal,
            taxAmount: $taxAmount,
            loanDeductionTotal: $loanDeductionTotal,
            netPay: $netPay,
            lines: $lines,
        );
    }

    /**
     * [Regulasi Pemerintah — bulan Desember selalu final] + [Inferensi:
     * karyawan resign/terminate di bulan berjalan juga final period, karena
     * tidak akan ada payroll run lagi buat dia setelah ini]. Employee->resign_date
     * sudah ada dari fitur Employment Lifecycle.
     */
    private function isFinalTaxPeriod(Employee $employee, PayrollRun $payrollRun): bool
    {
        if ($payrollRun->period_month === 12) {
            return true;
        }

        if (! $employee->resign_date) {
            return false;
        }

        return $employee->resign_date->year === $payrollRun->period_year
            && $employee->resign_date->month === $payrollRun->period_month;
    }

    /**
     * [Inferensi teknis - Kepmenaker 102/2004, disederhanakan]: jam pertama x
     * multiplier_first_hour, sisanya x multiplier_next_hours. Tidak
     * membedakan lembur hari kerja normal/libur mingguan/hari libur nasional —
     * DISENGAJA tidak direfactor di audit ini (di luar scope), tapi titik
     * panggilnya sengaja 1 method biar gampang diganti jadi rule-based nanti.
     */
    private function calculateOvertimePay(string $basicSalary, int $overtimeMinutes, CompanyPayrollAttendanceSetting $setting): string
    {
        $hourlyRate = PayrollMath::div($basicSalary, (string) $setting->overtime_hourly_divisor, 4);
        $totalHours = PayrollMath::div((string) $overtimeMinutes, '60', 4);

        if (bccomp($totalHours, '1', 4) <= 0) {
            return PayrollMath::mul($hourlyRate, PayrollMath::mul($totalHours, (string) $setting->overtime_multiplier_first_hour));
        }

        $firstHourPay = PayrollMath::mul($hourlyRate, (string) $setting->overtime_multiplier_first_hour);
        $remainingHours = bcsub($totalHours, '1', 4);
        $remainingPay = PayrollMath::mul($hourlyRate, PayrollMath::mul($remainingHours, (string) $setting->overtime_multiplier_next_hours));

        return PayrollMath::add($firstHourPay, $remainingPay);
    }

    private function estimateLateMinutes(Employee $employee, PayrollRun $payrollRun): int
    {
        $referenceDate = Carbon::createFromDate($payrollRun->period_year, $payrollRun->period_month, 1);
        $periodStart = $referenceDate->copy()->startOfMonth();
        $periodEnd = $payrollRun->cutoff_date ?? $referenceDate->copy()->endOfMonth();

        $rows = $this->attendanceReportService->dailyRecap($employee, $periodStart, $periodEnd);

        return collect($rows)->sum(fn ($row) => $row['approved_late_minutes'] ?? $row['late_minutes'] ?? 0);
    }
}