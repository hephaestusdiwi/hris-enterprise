<?php

namespace App\Modules\Interview\Enums;

enum InterviewResult: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case Hold = 'hold';
}