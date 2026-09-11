<?php

namespace App\Modules\JobVacancy\Enums;

enum WorkingType: string
{
    case Onsite = 'onsite';
    case Remote = 'remote';
    case Hybrid = 'hybrid';
}