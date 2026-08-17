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
     *
     * $subjectEmployee nullable — SpecificEmployee & SpecificRole self-contained
     * di ApprovalStep-nya sendiri, ga butuh subject. DirectManager tetap butuh
     * subject Employee (buat cari manager-nya) — kalau null, secara eksplisit
     * unresolvable (array kosong), BUKAN diam-diam pakai proxy lain. Ini yang
     * bikin business-process non-employee (Payroll Run, dst) aman pakai
     * DirectManager sebagai sinyal "flow ini salah dikonfigurasi", bukan
     * silently approve ke orang yang salah.
     */
    public function resolveApproverUserIds(ApprovalStep $step, ?Employee $subjectEmployee): array
    {
        return match ($step->approver_type) {
            ApproverType::DirectManager => $this->resolveDirectManager($subjectEmployee),
            ApproverType::SpecificEmployee => $this->resolveSpecificEmployee($step),
            ApproverType::SpecificRole => $this->resolveSpecificRole($step),
        };
    }

    private function resolveDirectManager(?Employee $subjectEmployee): array
    {
        if (! $subjectEmployee) {
            return [];
        }

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