<?php

namespace App\Modules\Payroll\DataTransferObjects;

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
    ) {
    }
}