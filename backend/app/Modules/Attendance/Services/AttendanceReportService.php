<?php

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Contracts\HolidayCheckerInterface;
use App\Modules\Attendance\Contracts\LeaveCheckerInterface;
use App\Modules\Attendance\Enums\AttendanceStatus;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Employee\Models\Employee;
use App\Modules\Shift\Models\Shift;
use App\Modules\WorkingSchedule\Contracts\WorkingScheduleResolverInterface;
use App\Modules\WorkingSchedule\Models\WorkingScheduleDetail;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class AttendanceReportService
{
    public function __construct(
        private WorkingScheduleResolverInterface $workingScheduleResolver,
        private HolidayCheckerInterface $holidayChecker,
        private LeaveCheckerInterface $leaveChecker,
    ) {
    }

    /**
     * @param Collection<int, Employee> $employees
     * @return array<int, array<string, mixed>>
     */
    public function summarize(Collection $employees, Carbon $dateFrom, Carbon $dateTo): array
    {
        $employeeIds = $employees->pluck('id')->all();

        $attendances = Attendance::whereIn('employee_id', $employeeIds)
            ->whereBetween('attendance_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->get()
            ->groupBy('employee_id');

        return $employees->map(function (Employee $employee) use ($attendances, $dateFrom, $dateTo) {
            $employeeAttendances = $attendances->get($employee->id, collect());

            return $this->summarizeEmployee($employee, $employeeAttendances, $dateFrom, $dateTo);
        })->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function dailyRecap(Employee $employee, Carbon $dateFrom, Carbon $dateTo): array
    {
        $attendances = Attendance::where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->with(['shift', 'approvalRequests'])
            ->get()
            ->keyBy(fn (Attendance $a) => $a->attendance_date->toDateString());

        $expectedShifts = $this->expectedShiftsByDate($employee, $dateFrom, $dateTo);
        $rows = [];

        foreach (CarbonPeriod::create($dateFrom, $dateTo) as $date) {
            $dateString = $date->toDateString();
            $attendance = $attendances->get($dateString);
            $expectedShift = $expectedShifts[$dateString] ?? null;
            $isHoliday = $this->holidayChecker->isHoliday($employee->company_id, $employee->branch_id, $date);

            $rows[] = [
                'date' => $dateString,
                'day_name' => $date->format('l'),
                'is_holiday' => $isHoliday,
                'shift' => $attendance?->shift ? [
                    'id' => $attendance->shift->id,
                    'name' => $attendance->shift->name,
                ] : ($expectedShift ? ['id' => $expectedShift->id, 'name' => $expectedShift->name] : null),
                'clock_in' => $attendance?->clock_in?->toDateTimeString(),
                'clock_out' => $attendance?->clock_out?->toDateTimeString(),
                'working_minutes' => ($attendance?->clock_in && $attendance->clock_out)
                    ? $attendance->clock_in->diffInMinutes($attendance->clock_out)
                    : null,
                'late_minutes' => $attendance?->late_minutes,
                'approved_late_minutes' => $attendance?->approved_late_minutes,
                'detected_overtime_minutes' => $attendance?->detected_overtime_minutes,
                'approved_overtime_minutes' => $attendance?->approved_overtime_minutes,
                'status' => $attendance?->status?->value
                    ?? ($this->leaveChecker->isOnLeave($employee->id, $date)
                        ? 'leave'
                        : ($expectedShift && ! $isHoliday ? 'absent' : null)),
                'clock_in_method' => $attendance?->clock_in_method,
                'clock_out_method' => $attendance?->clock_out_method,
                'approval_requests' => $attendance
                    ? $attendance->approvalRequests->map(fn ($r) => [
                        'type' => $r->type->value,
                        'status' => $r->status->value,
                        'detected_value' => $r->detected_value,
                        'approved_value' => $r->approved_value,
                    ])->all()
                    : [],
                'notes' => $attendance?->notes,
            ];
        }

        return $rows;
    }

    private function summarizeEmployee(Employee $employee, Collection $attendances, Carbon $dateFrom, Carbon $dateTo): array
    {
        $expectedShifts = $this->expectedShiftsByDate($employee, $dateFrom, $dateTo);
        $attendancesByDate = $attendances->keyBy(fn (Attendance $a) => $a->attendance_date->toDateString());

        $expectedWorkingDays = 0;
        $implicitLeaveDays = 0;
        $implicitAbsentDays = 0;

        foreach (CarbonPeriod::create($dateFrom, $dateTo) as $date) {
            $dateString = $date->toDateString();
            // Holiday check TIDAK berubah -- masih persis panggilan yang sama
            // seperti sebelumnya (company_id + branch_id + date).
            $isHoliday = $this->holidayChecker->isHoliday($employee->company_id, $employee->branch_id, $date);

            if (! isset($expectedShifts[$dateString]) || $isHoliday) {
                continue;
            }

            $expectedWorkingDays++;

            if ($attendancesByDate->has($dateString)) {
                // Ada Attendance record di hari kerja ini -- statusnya sudah
                // dihitung dari row Attendance itu sendiri di bawah
                // ($presentDays/$lateDays/dst), bukan dari cabang implicit.
                continue;
            }

            // Hari kerja TANPA Attendance record. Sebelum diasumsikan absent,
            // cek dulu approved leave -- ini bagian yang tadinya hilang
            // (LeaveCheckerInterface sebelumnya di-bind ke NullLeaveChecker
            // yang selalu false).
            if ($this->leaveChecker->isOnLeave($employee->id, $date)) {
                $implicitLeaveDays++;
            } else {
                $implicitAbsentDays++;
            }
        }

        $presentDays = $attendances->where('status', AttendanceStatus::Present)->count();
        $lateDays = $attendances->where('status', AttendanceStatus::Late)->count();
        $explicitLeaveDays = $attendances->where('status', AttendanceStatus::Leave)->count();
        $explicitAbsentDays = $attendances->where('status', AttendanceStatus::Absent)->count();
        $otherDays = $attendances->whereIn('status', [AttendanceStatus::HalfDay, AttendanceStatus::Sick, AttendanceStatus::Alpha])->count();

        $leaveDays = $explicitLeaveDays + $implicitLeaveDays;
        $absentDays = $explicitAbsentDays + $implicitAbsentDays;

        $overtimeMinutes = $attendances->sum(fn (Attendance $a) => $a->approved_overtime_minutes ?? $a->detected_overtime_minutes ?? 0);

        $workingMinutes = $attendances
            ->filter(fn (Attendance $a) => $a->clock_in && $a->clock_out)
            ->sum(fn (Attendance $a) => $a->clock_in->diffInMinutes($a->clock_out));

        $attendanceRate = $expectedWorkingDays > 0
            ? round((($presentDays + $lateDays) / $expectedWorkingDays) * 100, 2)
            : null;

        return [
            'employee' => [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'name' => trim("{$employee->first_name} {$employee->last_name}"),
                'photo_url' => $employee->photo_url,
            ],
            'present_days' => $presentDays,
            'late_days' => $lateDays,
            'overtime_minutes' => $overtimeMinutes,
            'leave_days' => $leaveDays,
            'absent_days' => $absentDays,
            'other_days' => $otherDays,
            'expected_working_days' => $expectedWorkingDays,
            'working_hours' => round($workingMinutes / 60, 2),
            'attendance_rate' => $attendanceRate,
        ];
    }

    /**
     * @return array<string, Shift>
     */
    private function expectedShiftsByDate(Employee $employee, Carbon $dateFrom, Carbon $dateTo): array
    {
        $workingScheduleId = $this->workingScheduleResolver->resolveWorkingScheduleId($employee);

        if (! $workingScheduleId) {
            return [];
        }

        $detailsByDayOfWeek = WorkingScheduleDetail::where('working_schedule_id', $workingScheduleId)
            ->with('shift')
            ->get()
            ->keyBy('day_of_week');

        $result = [];

        foreach (CarbonPeriod::create($dateFrom, $dateTo) as $date) {
            $detail = $detailsByDayOfWeek->get($date->dayOfWeekIso);

            if ($detail?->shift) {
                $result[$date->toDateString()] = $detail->shift;
            }
        }

        return $result;
    }
}