<?php

namespace App\Modules\Attendance\Services;

use App\Models\User;
use App\Modules\Attendance\Enums\AttendanceStatus;
use App\Modules\Attendance\Exceptions\AttendanceValidationException;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\AttendanceSetting\Models\AttendanceSetting;
use App\Modules\Employee\Models\Employee;
use App\Modules\Shift\Models\Shift;
use App\Modules\WorkingSchedule\Models\WorkingScheduleDetail;
use Carbon\Carbon;

class AttendanceService
{
    public function clockIn(User $user, ?float $latitude = null, ?float $longitude = null): Attendance
    {
        $employee = $this->resolveEmployeeForUser($user);
        $distance = $this->validateLocation($employee, $latitude, $longitude);

        return $this->doClockIn($employee, $latitude, $longitude, $distance);
    }

    public function clockOut(User $user, ?float $latitude = null, ?float $longitude = null): Attendance
    {
        $employee = $this->resolveEmployeeForUser($user);
        $distance = $this->validateLocation($employee, $latitude, $longitude);

        return $this->doClockOut($employee, $latitude, $longitude, $distance);
    }

    public function today(User $user): array
    {
        return $this->buildTodayPayload($this->resolveEmployeeForUser($user));
    }

    public function clockInForDevice(AttendanceDevice $device, string $employeeCode): Attendance
    {
        return $this->doClockIn($this->resolveEmployeeForDevice($device, $employeeCode), null, null, null);
    }

    public function clockOutForDevice(AttendanceDevice $device, string $employeeCode): Attendance
    {
        return $this->doClockOut($this->resolveEmployeeForDevice($device, $employeeCode), null, null, null);
    }

    public function todayForDevice(AttendanceDevice $device, string $employeeCode): array
    {
        return $this->buildTodayPayload($this->resolveEmployeeForDevice($device, $employeeCode));
    }

    private function doClockIn(Employee $employee, ?float $latitude, ?float $longitude, ?int $distanceMeters): Attendance
    {
        $attendance = $this->getTodayAttendance($employee);
        $shift = $this->resolveShiftForToday($employee);

        if ($attendance && $attendance->clock_in) {
            throw new AttendanceValidationException('Sudah melakukan clock-in hari ini.');
        }

        if (! $attendance) {
            $attendance = new Attendance([
                'employee_id' => $employee->id,
                'attendance_date' => Carbon::today()->toDateString(),
            ]);
        }

        $attendance->shift_id = $shift?->id;
        $attendance->clock_in = Carbon::now();
        $attendance->clock_in_latitude = $latitude;
        $attendance->clock_in_longitude = $longitude;
        $attendance->clock_in_distance_meters = $distanceMeters;
        $attendance->status = AttendanceStatus::Present;
        $attendance->save();

        return $attendance->load(['employee', 'shift']);
    }

    private function doClockOut(Employee $employee, ?float $latitude, ?float $longitude, ?int $distanceMeters): Attendance
    {
        $attendance = $this->getTodayAttendance($employee);

        if (! $attendance || ! $attendance->clock_in) {
            throw new AttendanceValidationException('Belum melakukan clock-in hari ini.');
        }

        if ($attendance->clock_out) {
            throw new AttendanceValidationException('Sudah melakukan clock-out hari ini.');
        }

        $attendance->clock_out = Carbon::now();
        $attendance->clock_out_latitude = $latitude;
        $attendance->clock_out_longitude = $longitude;
        $attendance->clock_out_distance_meters = $distanceMeters;
        $attendance->save();

        return $attendance->load(['employee', 'shift']);
    }

    private function buildTodayPayload(Employee $employee): array
    {
        $attendance = $this->getTodayAttendance($employee);
        $shift = $attendance?->shift_id
            ? $attendance->shift
            : $this->resolveShiftForToday($employee);

        return [
            'employee' => [
                'id' => $employee->id,
                'name' => trim("{$employee->first_name} {$employee->last_name}"),
            ],
            'attendance_date' => Carbon::today()->toDateString(),
            'status' => $attendance?->status?->value,
            'clock_in' => $attendance?->clock_in?->toDateTimeString(),
            'clock_in_distance_meters' => $attendance?->clock_in_distance_meters,
            'clock_out' => $attendance?->clock_out?->toDateTimeString(),
            'clock_out_distance_meters' => $attendance?->clock_out_distance_meters,
            'can_clock_in' => ! $attendance || ! $attendance->clock_in,
            'can_clock_out' => (bool) ($attendance && $attendance->clock_in && ! $attendance->clock_out),
            'shift' => $shift ? [
                'id' => $shift->id,
                'name' => $shift->name,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
            ] : null,
        ];
    }

    private function validateLocation(Employee $employee, ?float $latitude, ?float $longitude): ?int
    {
        $setting = $this->resolveAttendanceSetting($employee);

        if (! $setting || ! $setting->require_location) {
            return null;
        }

        if ($latitude === null || $longitude === null) {
            throw new AttendanceValidationException('Lokasi (GPS) wajib diisi untuk melakukan absen.');
        }

        if ($setting->office_latitude === null || $setting->office_longitude === null) {
            throw new AttendanceValidationException('Lokasi kantor belum diatur di Attendance Setting.');
        }

        $distance = $this->calculateDistanceMeters(
            (float) $setting->office_latitude,
            (float) $setting->office_longitude,
            $latitude,
            $longitude,
        );

        if ($distance > $setting->location_radius_meters) {
            throw new AttendanceValidationException(sprintf(
                'Lokasi Anda %d meter dari kantor, melebihi radius yang diizinkan (%d meter).',
                $distance,
                $setting->location_radius_meters,
            ));
        }

        return $distance;
    }

    private function calculateDistanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): int
    {
        $earthRadius = 6371000;

        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return (int) round($earthRadius * $c);
    }

    private function resolveEmployeeForUser(User $user): Employee
    {
        $employee = $user->employee;

        if (! $employee) {
            throw new AttendanceValidationException('User ini tidak terhubung dengan data employee.');
        }

        return $employee;
    }

    private function resolveEmployeeForDevice(AttendanceDevice $device, string $employeeCode): Employee
    {
        $employee = Employee::where('employee_number', $employeeCode)
            ->where('company_id', $device->company_id)
            ->when($device->branch_id, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->first();

        if (! $employee) {
            throw new AttendanceValidationException('Employee tidak ditemukan atau tidak terdaftar di device ini.');
        }

        return $employee;
    }

    private function getTodayAttendance(Employee $employee): ?Attendance
    {
        return Attendance::where('employee_id', $employee->id)
            ->where('attendance_date', Carbon::today()->toDateString())
            ->first();
    }

    private function resolveShiftForToday(Employee $employee): ?Shift
    {
        if (! $employee->working_schedule_id) {
            return null;
        }

        $detail = WorkingScheduleDetail::where('working_schedule_id', $employee->working_schedule_id)
            ->where('day_of_week', Carbon::today()->dayOfWeek)
            ->first();

        return $detail?->shift;
    }

    private function resolveAttendanceSetting(Employee $employee): ?AttendanceSetting
    {
        if ($employee->branch_id) {
            $branchSetting = AttendanceSetting::where('company_id', $employee->company_id)
                ->where('branch_id', $employee->branch_id)
                ->first();

            if ($branchSetting) {
                return $branchSetting;
            }
        }

        return AttendanceSetting::where('company_id', $employee->company_id)
            ->whereNull('branch_id')
            ->first();
    }
}