<?php

namespace App\Modules\Attendance\Services;

use App\Models\User;
use App\Modules\ApprovalFlow\Enums\ApproverType;
use App\Modules\ApprovalFlow\Models\ApprovalStep;
use App\Modules\Employee\Models\Employee;

class ApprovalStepApproverResolver
{
    /**
     * @return array<int, int> daftar user_id yang berhak mutusin step ini
     */
    public function resolveApproverUserIds(ApprovalStep $step, Employee $subjectEmployee): array
    {
        return match ($step->approver_type) {
            ApproverType::DirectManager => $this->resolveDirectManager($subjectEmployee),
            ApproverType::SpecificEmployee => $this->resolveSpecificEmployee($step),
            ApproverType::SpecificRole => $this->resolveSpecificRole($step),
        };
    }

    private function resolveDirectManager(Employee $subjectEmployee): array
    {
        $manager = $subjectEmployee->manager;

        return $manager?->user_id ? [$manager->user_id] : [];
    }

    private function resolveSpecificEmployee(ApprovalStep $step): array
    {
        $employee = $step->approverEmployee;

        return $employee?->user_id ? [$employee->user_id] : [];
    }

    private function resolveSpecificRole(ApprovalStep $step): array
    {
        if (! $step->approver_role_id) {
            return [];
        }

        return User::whereHas('roles', fn ($query) => $query->where('roles.id', $step->approver_role_id))
            ->pluck('id')
            ->all();
    }
}