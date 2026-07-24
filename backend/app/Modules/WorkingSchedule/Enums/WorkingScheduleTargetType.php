<?php

namespace App\Modules\WorkingSchedule\Enums;

enum WorkingScheduleTargetType: string
{
    case Company = 'company';
    case Branch = 'branch';
    case Department = 'department';
    case Position = 'position';
    case Employee = 'employee';
}