<?php

namespace App\Modules\Attendance\Strategies;

use App\Modules\Attendance\Contracts\AttendanceIdentificationStrategyInterface;
use App\Modules\Attendance\Exceptions\AttendanceValidationException;
use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Employee\Models\Employee;

class QrCodeIdentificationStrategy implements AttendanceIdentificationStrategyInterface
{
    public function identify(AttendanceDevice $device, array $payload): Employee
    {
        $qrToken = $payload['qr_token'] ?? null;

        if (! $qrToken) {
            throw new AttendanceValidationException('qr_token wajib diisi.');
        }

        $employee = Employee::where('qr_token', $qrToken)
            ->where('company_id', $device->company_id)
            ->when($device->branch_id, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->first();

        if (! $employee) {
            throw new AttendanceValidationException('QR Code tidak dikenali atau tidak terdaftar di device ini.');
        }

        return $employee;
    }
}