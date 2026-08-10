<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Pph21\DataTransferObjects\MonthlyTaxRecord;
use Carbon\Carbon;

/**
 * Payroll Run/Payslip adalah source of truth histori payroll (lihat prinsip
 * "jangan snapshot tambahan di Bpjs/Pph21"). Class ini yang menyuplai histori
 * itu ke TaxCalculationEngine::calculateAnnualReconciliation() sebagai
 * MonthlyTaxRecord[] — menutup Opsi A yang disepakati di STEP Pph21.
 */
class PayrollHistoryReader
{
    /**
     * @return array<int, MonthlyTaxRecord>
     */
    public function priorMonthsInYear(int $companyId, int $employeeId, int $taxYear, int $beforeMonth): array
    {
        $runs = PayrollRun::where('company_id', $companyId)
            ->where('period_year', $taxYear)
            ->where('period_month', '<', $beforeMonth)
            ->where('status', PayrollRunStatus::Locked->value)
            ->with(['currentRevision.payslips' => fn ($q) => $q->where('employee_id', $employeeId)->with('lines')])
            ->orderBy('period_month')
            ->get();

        $records = [];

        foreach ($runs as $run) {
            $payslip = $run->currentRevision?->payslips->first();

            if (! $payslip) {
                continue;
            }

            $pensionLine = $payslip->lines->first(
                fn ($line) => $line->type->value === 'bpjs_employee' && str_contains(strtolower($line->label), 'jht'),
            );

            $records[] = new MonthlyTaxRecord(
                periodDate: Carbon::createFromDate($run->period_year, $run->period_month, 1),
                grossIncome: (string) $payslip->gross_earning,
                pph21Withheld: (string) $payslip->tax_amount,
                pensionContribution: $pensionLine ? (string) $pensionLine->amount : '0.00',
            );
        }

        return $records;
    }
}