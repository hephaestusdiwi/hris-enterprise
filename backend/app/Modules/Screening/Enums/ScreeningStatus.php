<?php

namespace App\Modules\Screening\Enums;

enum ScreeningStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
}