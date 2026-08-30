<?php

namespace App\Modules\ChangeShiftRequest\Services;

use App\Modules\Attendance\Enums\AttendanceActivityType;
use App\Modules\Attendance\Services\AttendanceActivityService;
use App\Modules\ChangeShiftRequest\Enums\ChangeShiftRequestStatus;
use App\Modules\ChangeShiftRequest\Exceptions\ChangeShiftRequestValidationException;
use App\Modules\ChangeShiftRequest\Models\ChangeShiftRequest;
use App\Modules\Employee\Models\Employee;
use App\Modules\Shift\Models\Shift;
use App\Modules\WorkingSchedule\Contracts\WorkingScheduleResolverInterface;
use App\Modules\WorkingSchedule\Models\WorkingScheduleDetail;
use Carbon\Carbon;

class ChangeShiftRequestService
{
    public function __construct(
        private WorkingScheduleResolverInterface $workingScheduleResolver,
        private ChangeShiftRequestApprovalService $approvalService,
        private AttendanceActivityService $activityService,
    ) {
    }

    /**
     * @param array{attendance_date: string, requested_shift_id: int, reason: string} $data
     */
    public function submit(Employee $employee, array $data): ChangeShiftRequest
    {
        $attendanceDate = Carbon::parse($data['attendance_date'])->startOfDay();

        $this->assertNoPendingOrApprovedDuplicate($employee, $attendanceDate);

        $requestedShift = Shift::find($data['requested_shift_id']);

        if (! $requestedShift || $requestedShift->company_id !== $employee->company_id) {
            throw new ChangeShiftRequestValidationException('Shift tujuan tidak valid atau bukan milik company employee ini.');
        }

        $changeShiftRequest = ChangeShiftRequest::create([
            'employee_id' => $employee->id,
            'attendance_date' => $attendanceDate->toDateString(),
            'current_shift_id' => $this->resolveNormalShiftForDate($employee, $attendanceDate)?->id,
            'requested_shift_id' => $requestedShift->id,
            'reason' => $data['reason'],
            'status' => ChangeShiftRequestStatus::Pending->value,
            'requested_at' => now(),
        ]);

        $this->activityService->record(
            employeeId: $employee->id,
            type: AttendanceActivityType::ChangeShiftRequestSubmitted,
            actorUserId: $employee->user_id,
            metadata: [
                'attendance_date' => $changeShiftRequest->attendance_date->toDateString(),
                'requested_shift_id' => $requestedShift->id,
                'reason' => $data['reason'],
            ],
        );

        $this->approvalService->initiate($changeShiftRequest);

        return $changeShiftRequest->fresh();
    }

    public function cancel(ChangeShiftRequest $changeShiftRequest): ChangeShiftRequest
    {
        if ($changeShiftRequest->status !== ChangeShiftRequestStatus::Pending) {
            throw new ChangeShiftRequestValidationException('Hanya change shift request berstatus pending yang bisa dibatalkan.');
        }

        $changeShiftRequest->update([
            'status' => ChangeShiftRequestStatus::Cancelled->value,
            'decided_at' => now(),
        ]);

        $this->approvalService->cancelApprovalIfAny($changeShiftRequest);

        $this->activityService->record(
            employeeId: $changeShiftRequest->employee_id,
            type: AttendanceActivityType::ChangeShiftRequestCancelled,
            actorUserId: $changeShiftRequest->employee->user_id,
        );

        return $changeShiftRequest->fresh();
    }

    private function assertNoPendingOrApprovedDuplicate(Employee $employee, Carbon $attendanceDate): void
    {
        $exists = ChangeShiftRequest::where('employee_id', $employee->id)
            ->where('attendance_date', $attendanceDate->toDateString())
            ->whereIn('status', [ChangeShiftRequestStatus::Pending->value, ChangeShiftRequestStatus::Approved->value])
            ->exists();

        if ($exists) {
            throw new ChangeShiftRequestValidationException('Sudah ada change shift request pending/approved untuk tanggal ini.');
        }
    }

    private function resolveNormalShiftForDate(Employee $employee, Carbon $date): ?Shift
    {
        $workingScheduleId = $this->workingScheduleResolver->resolveWorkingScheduleId($employee);

        if (! $workingScheduleId) {
            return null;
        }

        return WorkingScheduleDetail::where('working_schedule_id', $workingScheduleId)
            ->where('day_of_week', $date->dayOfWeekIso)
            ->first()?->shift;
    }
}