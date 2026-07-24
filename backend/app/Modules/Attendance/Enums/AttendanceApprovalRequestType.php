<?php

namespace App\Modules\Attendance\Enums;

enum AttendanceApprovalRequestType: string
{
    case Late = 'late';
    case Overtime = 'overtime';
    case Correction = 'correction';
}