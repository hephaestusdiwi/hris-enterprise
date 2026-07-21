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
    public function clockIn(User $user): Attendance
    {
        return $this->doClockIn($this->resolveEmployeeForUser($user));
    }

    public function clockOut(User $user): Attendance
    {
        return $this->doClockOut($this->resolveEmployeeForUser($user));
    }

    public function today(User $user): array
    {
        return $this->buildTodayPayload($this->resolveEmployeeForUser($user));
    }

    public function clockInForDevice(AttendanceDevice $device, string $employeeCode): Attendance
    {
        return $this->doClockIn($this->resolveEmployeeForDevice($device, $employeeCode));
    }

    public function clockOutForDevice(AttendanceDevice $device, string $employeeCode): Attendance
    {
        return $this->doClockOut($this->resolveEmployeeForDevice($device, $employeeCode));
    }

    public function todayForDevice(AttendanceDevice $device, string $employeeCode): array
    {
        return $this->buildTodayPayload($this->resolveEmployeeForDevice($device, $employeeCode));
    }

    private function doClockIn(Employee $employee): Attendance
    {
        $attendance = $this->getTodayAttendance($employee);
        $shift = $this->resolveShiftForToday($employee);
        $this->resolveAttendanceSetting($employee); // di-load agar siap dipakai validasi GPS/foto di step berikutnya

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
        $attendance->status = AttendanceStatus::Present;
        $attendance->save();

        return $attendance->load(['employee', 'shift']);
    }

    private function doClockOut(Employee $employee): Attendance
    {
        $attendance = $this->getTodayAttendance($employee);

        if (! $attendance || ! $attendance->clock_in) {
            throw new AttendanceValidationException('Belum melakukan clock-in hari ini.');
        }

        if ($attendance->clock_out) {
            throw new AttendanceValidationException('Sudah melakukan clock-out hari ini.');
        }

        $attendance->clock_out = Carbon::now();
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
            'clock_out' => $attendance?->clock_out?->toDateTimeString(),
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