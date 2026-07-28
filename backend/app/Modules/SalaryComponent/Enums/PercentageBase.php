<?php

namespace App\Modules\SalaryComponent\Enums;

enum PercentageBase: string
{
    case BasicSalary = 'basic_salary';
    case GrossSalary = 'gross_salary';
}