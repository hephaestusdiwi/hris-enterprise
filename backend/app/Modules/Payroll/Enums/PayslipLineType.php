<?php

namespace App\Modules\Payroll\Enums;

enum PayslipLineType: string
{
    case Earning = 'earning';
    case Deduction = 'deduction';
    case BpjsEmployee = 'bpjs_employee';
    case BpjsEmployer = 'bpjs_employer';
    case Tax = 'tax';
    case LoanInstallment = 'loan_installment';

    public function label(): string
    {
        return match ($this) {
            self::Earning => 'Penambah',
            self::Deduction => 'Potongan',
            self::BpjsEmployee => 'BPJS (Karyawan)',
            self::BpjsEmployer => 'BPJS (Company)',
            self::Tax => 'PPh 21',
            self::LoanInstallment => 'Cicilan Loan',
        };
    }
}