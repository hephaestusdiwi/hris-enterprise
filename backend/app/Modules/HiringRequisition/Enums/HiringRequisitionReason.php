<?php

namespace App\Modules\HiringRequisition\Enums;

enum HiringRequisitionReason: string
{
    case NewPosition = 'new_position';
    case Replacement = 'replacement';
}