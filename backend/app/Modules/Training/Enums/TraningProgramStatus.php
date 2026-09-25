<?php

namespace App\Modules\Training\Enums;

enum TrainingProgramStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}