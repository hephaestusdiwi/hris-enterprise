<?php

namespace App\Modules\Screening\Enums;

enum ScreeningResult: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case Hold = 'hold';
}