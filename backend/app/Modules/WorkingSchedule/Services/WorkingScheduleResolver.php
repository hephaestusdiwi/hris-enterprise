<?php

namespace App\Modules\WorkingSchedule\Services;

use App\Modules\Employee\Models\Employee;
use App\Modules\WorkingSchedule\Contracts\WorkingScheduleResolverInterface;
use App\Modules\WorkingSchedule\Enums\WorkingScheduleTargetType;
use App\Modules\WorkingSchedule\Models\WorkingScheduleAssignment;

class WorkingScheduleResolver implements WorkingScheduleResolverInterface
{
    public function resolveWorkingScheduleId(Employee $employee): ?int
    {
        // Prioritas #1: override langsung per employee (kolom lama dari STEP 28, tetap dipakai aktif)
        if ($employee->working_schedule_id) {
            return $employee->working_schedule_id;
        }

        $assignment = ($employee->position_id ? $this->find(WorkingScheduleTargetType::Position, $employee->position_id) : null)
            ?? ($employee->department_id ? $this->find(WorkingScheduleTargetType::Department, $employee->department_id) : null)
            ?? ($employee->branch_id ? $this->find(WorkingScheduleTargetType::Branch, $employee->branch_id) : null)
            ?? $this->find(WorkingScheduleTargetType::Company, $employee->company_id);

        return $assignment?->working_schedule_id;
    }

    private function find(WorkingScheduleTargetType $targetType, int $targetId): ?WorkingScheduleAssignment
    {
        return WorkingScheduleAssignment::where('target_type', $targetType->value)
            ->where('target_id', $targetId)
            ->where('is_active', true)
            ->first();
    }
}