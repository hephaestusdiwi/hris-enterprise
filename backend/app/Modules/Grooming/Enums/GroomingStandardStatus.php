<?php

namespace App\Modules\Grooming\Enums;

enum GroomingStandardStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}