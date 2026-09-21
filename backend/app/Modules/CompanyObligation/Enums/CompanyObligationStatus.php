<?php

namespace App\Modules\CompanyObligation\Enums;

enum CompanyObligationStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}