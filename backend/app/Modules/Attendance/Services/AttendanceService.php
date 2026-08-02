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
use App\Modules\Attendance\Services\AttendanceApprovalService;
use App\Modules\WorkingSchedule\Contracts\WorkingScheduleResolverInterface;
use App\Modules\FaceRecognition\Contracts\FaceRecognitionServiceInterface;
use App\Modules\FaceRecognition\Exceptions\FaceRecognitionException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class AttendanceService
{
    public function __construct(
        private AttendanceIdentificationStrategyFactory $strategyFactory,
        private OfficeQrTokenService $officeQrTokenService,
        private AttendanceCalculationEngineInterface $calculationEngine,
        private AttendanceApprovalService $approvalService,
        private WorkingScheduleResolverInterface $workingScheduleResolver,
        private FaceRecognitionServiceInterface $faceRecognitionService,
    ) {
    }

    public function clockIn(User $user, ?float $latitude = null, ?float $longitude = null, ?string $officeQrToken = null, ?string $photoBase64 = null): Attendance
    {
        $employee = $this->resolveEmployeeForUser($user);
        [$method, $device] = $this->resolveSelfServiceMethod($employee, $officeQrToken);

        $distance = $method === AttendanceMethod::DynamicQr
            ? null
            : $this->validateLocation($employee, $latitude, $longitude);

        $photoPath = $this->resolveAndSavePhoto($employee, $photoBase64, 'in');

        return $this->doClockIn($employee, $latitude, $longitude, $distance, $method, $device, $photoPath);
    }

    public function clockOut(User $user, ?float $latitude = null, ?float $longitude = null, ?string $officeQrToken = null, ?string $photoBase64 = null): Attendance
    {
        $employee = $this->resolveEmployeeForUser($user);
        [$method, $device] = $this->resolveSelfServiceMethod($employee, $officeQrToken);

        $distance = $method === AttendanceMethod::DynamicQr
            ? null
            : $this->validateLocation($employee, $latitude, $longitude);

        $photoPath = $this->resolveAndSavePhoto($employee, $photoBase64, 'out');

        return $this->doClockOut($employee, $latitude, $longitude, $distance, $method, $device, $photoPath);
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
        ?string $photoPath = null,
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
        $attendance->clock_in_photo_path = $photoPath;
        $attendance->clock_in_device_id = $device?->id;
        $attendance->clock_in_branch_id = $device?->branch_id;
        $attendance->clock_in_company_id = $device?->company_id;
        $attendance->late_minutes = $calculation->lateMinutes;
        $attendance->within_grace = $calculation->withinGrace;
        $attendance->status = $calculation->status;
        $attendance->save();

        if ($calculation->status === AttendanceStatus::Late && $calculation->lateMinutes !== null) {
            $this->approvalService->handleLateDetected($attendance, $calculation->lateMinutes);
        }

        return $attendance->load(['employee', 'shift']);
    }

    private function doClockOut(
        Employee $employee,
        ?float $latitude,
        ?float $longitude,
        ?int $distanceMeters,
        AttendanceMethod $method,
        ?AttendanceDevice $device,
        ?string $photoPath = null,
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
        $attendance->clock_out_photo_path = $photoPath;
        $attendance->clock_out_device_id = $device?->id;
        $attendance->clock_out_branch_id = $device?->branch_id;
        $attendance->clock_out_company_id = $device?->company_id;
        $attendance->detected_overtime_minutes = $overtime->detectedOvertimeMinutes;
        $attendance->save();

        if ($overtime->detectedOvertimeMinutes !== null) {
            $this->approvalService->handleOvertimeDetected($attendance, $overtime->detectedOvertimeMinutes);
        }

        return $attendance->load(['employee', 'shift']);
    }

    private function buildTodayPayload(Employee $employee): array
    {
        $attendance = $this->getTodayAttendance($employee);
        $shift = $attendance?->shift_id
            ? $attendance->shift
            : $this->resolveShiftForToday($employee);
        $setting = $this->resolveAttendanceSetting($employee);

        return [
            'employee' => [
                'id' => $employee->id,
                'name' => trim("{$employee->first_name} {$employee->last_name}"),
            ],
            'requires_photo' => (bool) ($setting->require_photo ?? false),
            'requires_face_verification' => (bool) ($setting->require_face_verification ?? false),
            'requires_location' => (bool) ($setting->require_location ?? false),
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

    /**
     * Simpan foto (base64) kalau dikirim. Kalau Attendance Setting mewajibkan
     * foto atau verifikasi wajah tapi tidak ada foto yang dikirim, tolak dengan
     * error yang jelas alih-alih diam-diam lolos tanpa foto. Kalau verifikasi
     * wajah aktif, foto dicocokkan ke face_embedding milik employee sendiri
     * (1-ke-1) SEBELUM disimpan.
     */
    private function resolveAndSavePhoto(Employee $employee, ?string $photoBase64, string $type): ?string
    {
        $setting = $this->resolveAttendanceSetting($employee);
        $requiresPhoto = (bool) ($setting->require_photo ?? false);
        $requiresFaceVerification = (bool) ($setting->require_face_verification ?? false);

        if (($requiresPhoto || $requiresFaceVerification) && ! $photoBase64) {
            throw new AttendanceValidationException('Foto wajib diambil untuk melakukan absen ini.');
        }

        if (! $photoBase64) {
            return null;
        }

        $raw = preg_replace('/^data:image\/\w+;base64,/', '', $photoBase64);

        if ($requiresFaceVerification) {
            $this->verifyFace($employee, $raw);   // ✅ sudah bersih dari prefix
        }

        $decoded = base64_decode($raw);
        $image = @imagecreatefromstring($decoded);

        if (! $image) {
            throw new AttendanceValidationException('Format foto tidak valid.');
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $filename = "attendance/{$employee->id}/".now()->timestamp."-{$type}.webp";
        Storage::disk('public')->makeDirectory("attendance/{$employee->id}");
        imagewebp($image, Storage::disk('public')->path($filename), 85);
        imagedestroy($image);

        return $filename;
    }

    /**
     * Verifikasi 1-ke-1: cocokkan foto ke face_embedding milik employee yang
     * SEDANG LOGIN sendiri (beda dari FaceIdentificationStrategy yang dipakai
     * Kiosk — itu 1-ke-banyak karena device belum tahu siapa yang absen).
     */
    private function verifyFace(Employee $employee, string $photoBase64): void
    {
        if (! $employee->face_embedding) {
            throw new AttendanceValidationException('Wajah Anda belum terdaftar. Hubungi HR untuk mendaftarkan wajah terlebih dahulu.');
        }

        try {
            $liveness = $this->faceRecognitionService->liveness($photoBase64);
        } catch (FaceRecognitionException $e) {
            throw new AttendanceValidationException($e->getMessage());
        }

        if (! $liveness['is_live']) {
            throw new AttendanceValidationException('Verifikasi wajah gagal: terindikasi bukan wajah asli (kemungkinan foto/spoofing).');
        }

        try {
            $recognition = $this->faceRecognitionService->recognize($photoBase64, [[
                'employee_id' => $employee->id,
                'embedding' => $employee->face_embedding,
            ]]);
        } catch (FaceRecognitionException $e) {
            throw new AttendanceValidationException($e->getMessage());
        }

        if (! $recognition['is_match'] || (int) $recognition['employee_id'] !== $employee->id) {
            throw new AttendanceValidationException('Wajah tidak cocok dengan data yang terdaftar.');
        }
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
        $workingScheduleId = $this->workingScheduleResolver->resolveWorkingScheduleId($employee);

        if (! $workingScheduleId) {
            return null;
        }

        $detail = WorkingScheduleDetail::where('working_schedule_id', $workingScheduleId)
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