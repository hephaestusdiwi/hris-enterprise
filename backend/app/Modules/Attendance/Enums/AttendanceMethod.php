<?php

namespace App\Modules\Attendance\Enums;

enum AttendanceMethod: string
{
    case SelfService = 'self_service';
    case DynamicQr = 'dynamic_qr';
    case DeviceEmployeeCode = 'device_employee_code';
    case DeviceFace = 'device_face';
    case DeviceQrCard = 'device_qr_card';

    public function label(): string
    {
        return match ($this) {
            self::SelfService => 'Self-Service (App)',
            self::DynamicQr => 'Dynamic Office QR',
            self::DeviceEmployeeCode => 'Device - Employee Code',
            self::DeviceFace => 'Device - Face Recognition',
            self::DeviceQrCard => 'Device - Employee QR Card',
        };
    }
}