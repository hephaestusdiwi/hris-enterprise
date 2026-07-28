<?php

namespace App\Modules\LeaveRequest\Services;

use App\Modules\Attendance\Contracts\HolidayCheckerInterface;
use App\Modules\Attendance\Services\ApprovalStepApproverResolver;
use App\Modules\Employee\Models\Employee;
use App\Modules\LeaveBalance\Models\LeaveBalance;
use App\Modules\LeaveBalance\Services\LeaveBalanceEligibilityService;
use App\Modules\LeaveBalance\Support\LeaveBalanceMath;
use App\Modules\LeaveRequest\Enums\LeaveRequestStatus;
use App\Modules\LeaveRequest\Exceptions\LeaveRequestValidationException;
use App\Modules\LeaveRequest\Models\LeaveRequest;
use App\Modules\LeaveType\Models\LeaveType;
use App\Modules\WorkingSchedule\Contracts\WorkingScheduleResolverInterface;
use App\Modules\WorkingSchedule\Models\WorkingScheduleDetail;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class LeaveRequestService
{
    public function __construct(
        private LeaveBalanceEligibilityService $eligibilityService,
        private WorkingScheduleResolverInterface $workingScheduleResolver,
        private HolidayCheckerInterface $holidayChecker,
        private LeaveApprovalService $approvalService,
    ) {
    }

    /**
     * @param array{start_date: string, end_date: string, is_half_day?: bool, half_day_session?: ?string,
     *              start_time?: ?string, end_time?: ?string, reason: string, attachment_path?: ?string} $data
     */
    public function submit(Employee $employee, LeaveType $leaveType, array $data): LeaveRequest
    {
        $startDate = Carbon::parse($data['start_date'])->startOfDay();
        $endDate = Carbon::parse($data['end_date'])->startOfDay();

        if (! $this->eligibilityService->meetsServiceRequirement($employee, $leaveType, $startDate)) {
            throw new LeaveRequestValidationException('Employee belum memenuhi syarat untuk leave type ini.');
        }

        $this->assertNoOverlap($employee, $startDate, $endDate);

        $isHalfDay = (bool) ($data['is_half_day'] ?? false);
        $isHourly = ! empty($data['start_time']) && ! empty($data['end_time']);

        if ($isHalfDay && ! $leaveType->allow_half_day) {
            throw new LeaveRequestValidationException('Leave type ini tidak mengizinkan pengajuan setengah hari.');
        }

        if ($isHourly && ! $leaveType->allow_hourly) {
            throw new LeaveRequestValidationException('Leave type ini tidak mengizinkan pengajuan per jam.');
        }

        if ($leaveType->requires_attachment && empty($data['attachment_path'])) {
            throw new LeaveRequestValidationException('Leave type ini mewajibkan lampiran.');
        }

        $totalDays = $this->calculateTotalDays(
            $employee,
            $startDate,
            $endDate,
            $isHalfDay,
            $isHourly,
            $data['start_time'] ?? null,
            $data['end_time'] ?? null,
        );

        $leaveBalance = null;

        if ($leaveType->requires_balance) {
            $leaveBalance = $this->findBalance($employee, $leaveType, $startDate);

            if (! $leaveBalance) {
                throw new LeaveRequestValidationException('Leave balance tidak ditemukan untuk periode ini.');
            }

            $this->assertSufficientBalance($leaveBalance, $totalDays);
        }

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'leave_balance_id' => $leaveBalance?->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'is_half_day' => $isHalfDay,
            'half_day_session' => $data['half_day_session'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'total_days' => $totalDays,
            'reason' => $data['reason'],
            'attachment_path' => $data['attachment_path'] ?? null,
            'status' => LeaveRequestStatus::Pending->value,
            'requested_at' => now(),
        ]);

        if ($leaveType->requires_approval) {
            $this->approvalService->initiate($leaveRequest);
        } else {
            $this->approvalService->autoApprove($leaveRequest);
        }

        return $leaveRequest->fresh();
    }

    public function cancel(LeaveRequest $leaveRequest): LeaveRequest
    {
        if ($leaveRequest->status !== LeaveRequestStatus::Pending) {
            throw new LeaveRequestValidationException('Hanya leave request berstatus pending yang bisa dibatalkan.');
        }

        $leaveRequest->update([
            'status' => LeaveRequestStatus::Cancelled->value,
            'decided_at' => now(),
        ]);

        $this->approvalService->cancelApprovalIfAny($leaveRequest);

        return $leaveRequest->fresh();
    }

    private function assertNoOverlap(Employee $employee, Carbon $startDate, Carbon $endDate): void
    {
        $overlap = LeaveRequest::where('employee_id', $employee->id)
            ->whereIn('status', [LeaveRequestStatus::Pending->value, LeaveRequestStatus::Approved->value])
            ->where('start_date', '<=', $endDate->toDateString())
            ->where('end_date', '>=', $startDate->toDateString())
            ->exists();

        if ($overlap) {
            throw new LeaveRequestValidationException('Anda sudah memiliki leave request lain yang tanggalnya overlap.');
        }
    }

    private function calculateTotalDays(
        Employee $employee,
        Carbon $startDate,
        Carbon $endDate,
        bool $isHalfDay,
        bool $isHourly,
        ?string $startTime,
        ?string $endTime,
    ): string {
        if ($isHalfDay) {
            return '0.50';
        }

        if ($isHourly) {
            return $this->calculateHourlyDays($employee, $startDate, $startTime, $endTime);
        }

        $workingDayCount = 0;

        foreach (CarbonPeriod::create($startDate, $endDate) as $date) {
            if ($this->isWorkingDay($employee, $date)) {
                $workingDayCount++;
            }
        }

        return number_format($workingDayCount, 2, '.', '');
    }

    private function isWorkingDay(Employee $employee, Carbon $date): bool
    {
        if ($this->holidayChecker->isHoliday($employee->company_id, $employee->branch_id, $date)) {
            return false;
        }

        $workingScheduleId = $this->workingScheduleResolver->resolveWorkingScheduleId($employee);

        if (! $workingScheduleId) {
            return false;
        }

        return WorkingScheduleDetail::where('working_schedule_id', $workingScheduleId)
            ->where('day_of_week', $date->dayOfWeek)
            ->exists();
    }

    private function calculateHourlyDays(Employee $employee, Carbon $date, string $startTime, string $endTime): string
    {
        $workingScheduleId = $this->workingScheduleResolver->resolveWorkingScheduleId($employee);

        if (! $workingScheduleId) {
            throw new LeaveRequestValidationException('Employee tidak memiliki jadwal kerja pada tanggal ini.');
        }

        $detail = WorkingScheduleDetail::where('working_schedule_id', $workingScheduleId)
            ->where('day_of_week', $date->dayOfWeek)
            ->with('shift')
            ->first();

        if (! $detail?->shift) {
            throw new LeaveRequestValidationException('Employee tidak memiliki shift pada tanggal ini, tidak bisa mengajukan cuti per jam.');
        }

        $shift = $detail->shift;
        $shiftStart = Carbon::parse($shift->start_time);
        $shiftEnd = Carbon::parse($shift->end_time);

        if ($shift->is_overnight || $shiftEnd->lte($shiftStart)) {
            $shiftEnd->addDay();
        }

        $shiftDurationHours = $shiftStart->diffInMinutes($shiftEnd) / 60;

        if ($shiftDurationHours <= 0) {
            throw new LeaveRequestValidationException('Durasi shift tidak valid untuk perhitungan cuti per jam.');
        }

        $requestStart = Carbon::parse($startTime);
        $requestEnd = Carbon::parse($endTime);

        if ($requestEnd->lte($requestStart)) {
            throw new LeaveRequestValidationException('Jam selesai harus setelah jam mulai.');
        }

        $requestedHours = $requestStart->diffInMinutes($requestEnd) / 60;

        $fraction = LeaveBalanceMath::div((string) $requestedHours, (string) $shiftDurationHours);

        return LeaveBalanceMath::mul('1', $fraction);
    }

    private function findBalance(Employee $employee, LeaveType $leaveType, Carbon $referenceDate): ?LeaveBalance
    {
        return LeaveBalance::where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('period_start', '<=', $referenceDate->toDateString())
            ->where('period_end', '>=', $referenceDate->toDateString())
            ->first();
    }

    private function assertSufficientBalance(LeaveBalance $leaveBalance, string $totalDays): void
    {
        $pendingSum = LeaveRequest::where('leave_balance_id', $leaveBalance->id)
            ->where('status', LeaveRequestStatus::Pending->value)
            ->get()
            ->reduce(fn (string $carry, LeaveRequest $r) => LeaveBalanceMath::add($carry, (string) $r->total_days), '0.00');

        $remaining = $leaveBalance->remainingDaysAsString() ?? '0.00';
        $available = LeaveBalanceMath::sub($remaining, $pendingSum);

        if (! LeaveBalanceMath::gte($available, $totalDays)) {
            throw new LeaveRequestValidationException("Saldo cuti tidak cukup. Tersedia: {$available} hari, diajukan: {$totalDays} hari.");
        }
    }
}