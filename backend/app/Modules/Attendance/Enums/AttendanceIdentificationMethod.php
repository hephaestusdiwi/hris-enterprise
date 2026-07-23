<?php

namespace App\Modules\Attendance\Enums;

enum AttendanceIdentificationMethod: string
{
    case EmployeeCode = 'employee_code';
    case Face = 'face';
    case Qr = 'qr';

    // Nanti nambah di sini pas step RFID / Fingerprint:
    // case Rfid = 'rfid';
    // case Fingerprint = 'fingerprint';
}