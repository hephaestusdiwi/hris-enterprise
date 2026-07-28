<?php

namespace App\Modules\SalaryComponent\Enums;

enum SalaryComponentCategory: string
{
    case BasicSalary = 'basic_salary';
    case Allowance = 'allowance';
    case Deduction = 'deduction';
    case Statutory = 'statutory';
}