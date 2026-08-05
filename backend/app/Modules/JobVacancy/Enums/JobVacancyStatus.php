<?php

namespace App\Modules\JobVacancy\Enums;

enum JobVacancyStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Paused = 'paused';
    case Closed = 'closed';
    case Filled = 'filled';
    case Cancelled = 'cancelled';
    case Archived = 'archived';
}