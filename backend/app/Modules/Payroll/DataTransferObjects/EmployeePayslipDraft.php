<?php

namespace App\Modules\Payroll\DataTransferObjects;

use App\Modules\Pph21\DataTransferObjects\AnnualReconciliationResult;

final class EmployeePayslipDraft
{
    /**
     * @param  array<int, PayslipLineDraft>  $lines
     */
    public function __construct(
        public readonly int $employeeId,
        public readonly string $grossEarning,
        public readonly string $structuralDeduction,
        public readonly string $manualDeductionTotal,
        public readonly string $bpjsEmployeeTotal,
        public readonly string $bpjsEmployerTotal,
        public readonly string $taxAmount,
        public readonly string $loanDeductionTotal,
        public readonly string $netPay,
        public readonly array $lines,
        // Cuma keisi kalau periode ini adalah final tax period
        // (Desember/resign) DAN TaxCalculationEngine berhasil menghasilkan
        // rekonsiliasi (bukan null). PayrollRunService::proceedPayslip()
        // pakai field ini buat persist EmployeeAnnualTaxReconciliation —
        // lihat migration create_employee_annual_tax_reconciliations_table.
        public readonly ?AnnualReconciliationResult $annualReconciliation = null,
    ) {
    }
}