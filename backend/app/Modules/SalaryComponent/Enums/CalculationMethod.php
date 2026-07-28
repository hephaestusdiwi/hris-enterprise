<?php

namespace App\Modules\SalaryComponent\Enums;

enum CalculationMethod: string
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';
}