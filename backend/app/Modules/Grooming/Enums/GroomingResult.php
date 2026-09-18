<?php

namespace App\Modules\Grooming\Enums;

enum GroomingResult: string
{
    case Pass = 'pass';
    case NotPass = 'not_pass';
}