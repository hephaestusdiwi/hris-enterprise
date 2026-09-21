<?php

namespace App\Modules\CompanyObligation\Enums;

enum CompanyObligationRecipientType: string
{
    case User = 'user';
    case Pic = 'pic';
    case Role = 'role';
}