<?php

namespace App\Modules\Attendance\Strategies;

use App\Modules\Attendance\Contracts\AttendanceIdentificationStrategyInterface;
use App\Modules\Attendance\Exceptions\AttendanceValidationException;
use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Employee\Models\Employee;

class EmployeeCodeIdentificationStrategy implements AttendanceIdentificationStrategyInterface
{
    public function identify(AttendanceDevice $device, array $payload): Employee
    {
        $employeeCode = $payload['employee_code'] ?? null;

        if (! $employeeCode) {
            throw new AttendanceValidationException('employee_code wajib diisi.');
        }

        $employee = Employee::where('employee_number', $employeeCode)
            ->where('company_id', $device->company_id)
            ->when($device->branch_id, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->first();

        if (! $employee) {
            throw new AttendanceValidationException('Employee tidak ditemukan atau tidak terdaftar di device ini.');
        }

        return $employee;
    }
}