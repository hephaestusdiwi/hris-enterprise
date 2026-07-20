<?php

namespace App\Modules\Attendance\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case Absent = 'absent';
    case HalfDay = 'half_day';
    case Leave = 'leave';
    case Sick = 'sick';
    case Alpha = 'alpha';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Late => 'Late',
            self::Absent => 'Absent',
            self::HalfDay => 'Half Day',
            self::Leave => 'Leave',
            self::Sick => 'Sick',
            self::Alpha => 'Alpha',
        };
    }
}