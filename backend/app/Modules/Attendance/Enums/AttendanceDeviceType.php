<?php

namespace App\Modules\Attendance\Enums;

enum AttendanceDeviceType: string
{
    case Kiosk = 'kiosk';
    case FaceRecognition = 'face_recognition';
    case Fingerprint = 'fingerprint';
    case QrScanner = 'qr_scanner';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Kiosk => 'Tablet Kiosk',
            self::FaceRecognition => 'Face Recognition Terminal',
            self::Fingerprint => 'Fingerprint Device',
            self::QrScanner => 'QR Scanner',
            self::Other => 'Lainnya',
        };
    }
}