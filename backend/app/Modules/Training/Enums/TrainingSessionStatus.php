<?php

namespace App\Modules\Training\Enums;

enum TrainingSessionStatus: string
{
    case Scheduled = 'scheduled';
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}