<?php

namespace App\Modules\Payroll\Enums;

enum PayslipLineSource: string
{
    case SalaryStructure = 'salary_structure';
    case Allowance = 'allowance';
    case Deduction = 'deduction';
    case Attendance = 'attendance';
    case Loan = 'loan';
    case Bpjs = 'bpjs';
    case Pph21 = 'pph21';
}