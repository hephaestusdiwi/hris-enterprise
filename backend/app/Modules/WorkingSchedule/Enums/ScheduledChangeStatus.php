<?php

namespace App\Modules\WorkingSchedule\Enums;

enum ScheduledChangeStatus: string
{
    case Pending = 'pending';
    case Applied = 'applied';
    case Cancelled = 'cancelled';
}