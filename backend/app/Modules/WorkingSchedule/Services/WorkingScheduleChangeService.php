<?php

namespace App\Modules\WorkingSchedule\Services;

use App\Modules\Employee\Models\Employee;
use App\Modules\WorkingSchedule\Enums\ScheduledChangeStatus;
use App\Modules\WorkingSchedule\Enums\WorkingScheduleTargetType;
use App\Modules\WorkingSchedule\Exceptions\WorkingScheduleChangeException;
use App\Modules\WorkingSchedule\Models\WorkingScheduleAssignment;
use App\Modules\WorkingSchedule\Models\WorkingScheduleScheduledChange;
use Carbon\Carbon;

class WorkingScheduleChangeService
{
    public function schedule(
        string $targetType,
        int $targetId,
        int $workingScheduleId,
        Carbon $effectiveDate,
        ?int $createdByUserId,
        ?string $notes,
    ): WorkingScheduleScheduledChange {
        $change = WorkingScheduleScheduledChange::create([
            'target_type' => $targetType,
            'target_id' => $targetId,
            'working_schedule_id' => $workingScheduleId,
            'effective_date' => $effectiveDate->toDateString(),
            'status' => ScheduledChangeStatus::Pending->value,
            'created_by_user_id' => $createdByUserId,
            'notes' => $notes,
        ]);

        if (! $effectiveDate->isFuture()) {
            $this->applyChange($change);
        }

        return $change->fresh();
    }

    public function applyDueChanges(): int
    {
        $changes = WorkingScheduleScheduledChange::where('status', ScheduledChangeStatus::Pending->value)
            ->whereDate('effective_date', '<=', Carbon::today())
            ->orderBy('effective_date')
            ->orderBy('id')
            ->get();

        foreach ($changes as $change) {
            $this->applyChange($change);
        }

        return $changes->count();
    }

    public function applyChange(WorkingScheduleScheduledChange $change): void
    {
        if ($change->target_type === WorkingScheduleTargetType::Employee->value) {
            Employee::where('id', $change->target_id)->update([
                'working_schedule_id' => $change->working_schedule_id,
            ]);
        } else {
            WorkingScheduleAssignment::where('target_type', $change->target_type)
                ->where('target_id', $change->target_id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            WorkingScheduleAssignment::create([
                'working_schedule_id' => $change->working_schedule_id,
                'target_type' => $change->target_type,
                'target_id' => $change->target_id,
                'is_active' => true,
            ]);
        }

        $change->update([
            'status' => ScheduledChangeStatus::Applied->value,
            'applied_at' => now(),
        ]);
    }

    public function cancel(WorkingScheduleScheduledChange $change): void
    {
        if ($change->status !== ScheduledChangeStatus::Pending) {
            throw new WorkingScheduleChangeException('Hanya perubahan berstatus pending yang bisa dibatalkan.');
        }

        $change->update(['status' => ScheduledChangeStatus::Cancelled->value]);
    }

    public function resolveNextChange(Employee $employee): ?WorkingScheduleScheduledChange
    {
        return $this->findPending(WorkingScheduleTargetType::Employee, $employee->id)
            ?? ($employee->position_id ? $this->findPending(WorkingScheduleTargetType::Position, $employee->position_id) : null)
            ?? ($employee->department_id ? $this->findPending(WorkingScheduleTargetType::Department, $employee->department_id) : null)
            ?? ($employee->branch_id ? $this->findPending(WorkingScheduleTargetType::Branch, $employee->branch_id) : null)
            ?? $this->findPending(WorkingScheduleTargetType::Company, $employee->company_id);
    }

    private function findPending(WorkingScheduleTargetType $targetType, int $targetId): ?WorkingScheduleScheduledChange
    {
        return WorkingScheduleScheduledChange::where('target_type', $targetType->value)
            ->where('target_id', $targetId)
            ->where('status', ScheduledChangeStatus::Pending->value)
            ->where('effective_date', '>', Carbon::today())
            ->orderBy('effective_date')
            ->with('workingSchedule')
            ->first();
    }
}