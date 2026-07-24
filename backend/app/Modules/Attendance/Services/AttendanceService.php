<?php

namespace App\Modules\Attendance\Services;

use App\Models\User;
use App\Modules\Attendance\Enums\AttendanceIdentificationMethod;
use App\Modules\Attendance\Enums\AttendanceMethod;
use App\Modules\Attendance\Enums\AttendanceStatus;
use App\Modules\Attendance\Exceptions\AttendanceValidationException;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Attendance\Strategies\AttendanceIdentificationStrategyFactory;
use App\Modules\AttendanceSetting\Models\AttendanceSetting;
use App\Modules\Employee\Models\Employee;
use App\Modules\Shift\Models\Shift;
use App\Modules\WorkingSchedule\Models\WorkingScheduleDetail;
use App\Modules\Attendance\Contracts\AttendanceCalculationEngineInterface;
use Carbon\Carbon;

class AttendanceService
{
    public function __construct(
        private AttendanceIdentificationStrategyFactory $strategyFactory,
        private OfficeQrTokenService $officeQrTokenService,
        private AttendanceCalculationEngineInterface $calculationEngine,
    ) {
    }

    public function clockIn(User $user, ?float $latitude = null, ?float $longitude = null, ?string $officeQrToken = null): Attendance
    {
        $employee = $this->resolveEmployeeForUser($user);
        [$method, $device] = $this->resolveSelfServiceMethod($employee, $officeQrToken);

        $distance = $method === AttendanceMethod::DynamicQr
            ? null
            : $this->validateLocation($employee, $latitude, $longitude);

        return $this->doClockIn($employee, $latitude, $longitude, $distance, $method, $device);
    }

    public function clockOut(User $user, ?float $latitude = null, ?float $longitude = null, ?string $officeQrToken = null): Attendance
    {
        $employee = $this->resolveEmployeeForUser($user);
        [$method, $device] = $this->resolveSelfServiceMethod($employee, $officeQrToken);

        $distance = $method === AttendanceMethod::DynamicQr
            ? null
            : $this->validateLocation($employee, $latitude, $longitude);

        return $this->doClockOut($employee, $latitude, $longitude, $distance, $method, $device);
    }

    public function today(User $user): array
    {
        return $this->buildTodayPayload($this->resolveEmployeeForUser($user));
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function todayForDeviceUsing(AttendanceDevice $device, AttendanceIdentificationMethod $method, array $payload): array
    {
        return $this->buildTodayPayload($this->identifyForDevice($device, $method, $payload));
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function clockInForDeviceUsing(AttendanceDevice $device, AttendanceIdentificationMethod $method, array $payload): Attendance
    {
        $employee = $this->identifyForDevice($device, $method, $payload);
        $attendanceMethod = $this->mapIdentificationMethod($method);

        return $this->doClockIn($employee, null, null, null, $attendanceMethod, $device);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function clockOutForDeviceUsing(AttendanceDevice $device, AttendanceIdentificationMethod $method, array $payload): Attendance
    {
        $employee = $this->identifyForDevice($device, $method, $payload);
        $attendanceMethod = $this->mapIdentificationMethod($method);

        return $this->doClockOut($employee, null, null, null, $attendanceMethod, $device);
    }

    public function generateOfficeQr(AttendanceDevice $device): array
    {
        return $this->officeQrTokenService->generate($device);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function identifyForDevice(AttendanceDevice $device, AttendanceIdentificationMethod $method, array $payload): Employee
    {
        return $this->strategyFactory->make($method)->identify($device, $payload);
    }

    private function mapIdentificationMethod(AttendanceIdentificationMethod $method): AttendanceMethod
    {
        return match ($method) {
            AttendanceIdentificationMethod::EmployeeCode => AttendanceMethod::DeviceEmployeeCode,
            AttendanceIdentificationMethod::Face => AttendanceMethod::DeviceFace,
            AttendanceIdentificationMethod::Qr => AttendanceMethod::DeviceQrCard,
        };
    }

    /**
     * @return array{0: AttendanceMethod, 1: ?AttendanceDevice}
     */
    private function resolveSelfServiceMethod(Employee $employee, ?string $officeQrToken): array
    {
        if (! $officeQrToken) {
            return [AttendanceMethod::SelfService, null];
        }

        $deviceId = $this->officeQrTokenService->resolveDeviceId($officeQrToken);

        if (! $deviceId) {
            throw new AttendanceValidationException('QR kantor sudah kedaluwarsa atau tidak valid, silakan scan ulang.');
        }

        $device = AttendanceDevice::find($deviceId);

        if (! $device || ! $device->is_active) {
            throw new AttendanceValidationException('Attendance Device untuk QR ini tidak ditemukan atau nonaktif.');
        }

        if ($device->company_id !== $employee->company_id) {
            throw new AttendanceValidationException('QR ini bukan untuk company Anda.');
        }

        return [AttendanceMethod::DynamicQr, $device];
    }

    private function doClockIn(
        Employee $employee,
        ?float $latitude,
        ?float $longitude,
        ?int $distanceMeters,
        AttendanceMethod $method,
        ?AttendanceDevice $device,
    ): Attendance {
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

        $clockInAt = Carbon::now();
        $calculation = $this->calculationEngine->calculateClockIn($employee, Carbon::today(), $clockInAt, $shift);

        $attendance->shift_id = $shift?->id;
        $attendance->clock_in = $clockInAt;
        $attendance->clock_in_latitude = $latitude;
        $attendance->clock_in_longitude = $longitude;
        $attendance->clock_in_distance_meters = $distanceMeters;
        $attendance->clock_in_method = $method->value;
        $attendance->clock_in_device_id = $device?->id;
        $attendance->clock_in_branch_id = $device?->branch_id;
        $attendance->clock_in_company_id = $device?->company_id;
        $attendance->late_minutes = $calculation->lateMinutes;
        $attendance->within_grace = $calculation->withinGrace;
        $attendance->status = $calculation->status;
        $attendance->save();

        return $attendance->load(['employee', 'shift']);
    }

    private function doClockOut(
    Employee $employee,
    ?float $latitude,
    ?float $longitude,
    ?int $distanceMeters,
    AttendanceMethod $method,
    ?AttendanceDevice $device,
    ): Attendance {
        $attendance = $this->getTodayAttendance($employee);

        if (! $attendance || ! $attendance->clock_in) {
            throw new AttendanceValidationException('Belum melakukan clock-in hari ini.');
        }

        if ($attendance->clock_out) {
            throw new AttendanceValidationException('Sudah melakukan clock-out hari ini.');
        }

        $clockOutAt = Carbon::now();
        $shift = $attendance->shift_id ? $attendance->shift : null;
        $overtime = $this->calculationEngine->calculateClockOut($employee, Carbon::today(), $clockOutAt, $shift);

        $attendance->clock_out = $clockOutAt;
        $attendance->clock_out_latitude = $latitude;
        $attendance->clock_out_longitude = $longitude;
        $attendance->clock_out_distance_meters = $distanceMeters;
        $attendance->clock_out_method = $method->value;
        $attendance->clock_out_device_id = $device?->id;
        $attendance->clock_out_branch_id = $device?->branch_id;
        $attendance->clock_out_company_id = $device?->company_id;
        $attendance->detected_overtime_minutes = $overtime->detectedOvertimeMinutes;
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
            'clock_in_method' => $attendance?->clock_in_method,
            'late_minutes' => $attendance?->late_minutes,
            'within_grace' => $attendance?->within_grace,
            'clock_out' => $attendance?->clock_out?->toDateTimeString(),
            'clock_out_distance_meters' => $attendance?->clock_out_distance_meters,
            'clock_out_method' => $attendance?->clock_out_method,
            'detected_overtime_minutes' => $attendance?->detected_overtime_minutes,
            'approved_overtime_minutes' => $attendance?->approved_overtime_minutes,
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