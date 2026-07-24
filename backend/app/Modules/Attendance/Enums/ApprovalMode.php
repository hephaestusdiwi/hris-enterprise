<?php

namespace App\Modules\Attendance\Enums;

enum ApprovalMode: string
{
    case Automatic = 'automatic';
    case Manual = 'manual';
    case Disabled = 'disabled';
}