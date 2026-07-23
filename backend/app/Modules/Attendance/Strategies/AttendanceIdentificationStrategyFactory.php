<?php

namespace App\Modules\Attendance\Strategies;

use App\Modules\Attendance\Contracts\AttendanceIdentificationStrategyInterface;
use App\Modules\Attendance\Enums\AttendanceIdentificationMethod;
use App\Modules\Attendance\Exceptions\AttendanceValidationException;

class AttendanceIdentificationStrategyFactory
{
    public function __construct(
        private EmployeeCodeIdentificationStrategy $employeeCodeStrategy,
        private FaceIdentificationStrategy $faceStrategy,
        private QrCodeIdentificationStrategy $qrCodeStrategy,
    ) {
    }

    public function make(AttendanceIdentificationMethod $method): AttendanceIdentificationStrategyInterface
    {
        return match ($method) {
            AttendanceIdentificationMethod::EmployeeCode => $this->employeeCodeStrategy,
            AttendanceIdentificationMethod::Face => $this->faceStrategy,
            AttendanceIdentificationMethod::Qr => $this->qrCodeStrategy,
            default => throw new AttendanceValidationException('Metode identifikasi belum didukung.'),
        };
    }
}