<?php

namespace App\Modules\EmployeeMovement\Services;

use App\Modules\Employee\Models\Employee;
use App\Modules\EmployeeMovement\Enums\EmployeeMovementStatus;
use App\Modules\EmployeeMovement\Enums\EmployeeMovementType;
use App\Modules\EmployeeMovement\Exceptions\EmployeeMovementException;
use App\Modules\EmployeeMovement\Models\EmployeeMovement;

/**
 * Satu-satunya tempat yang benar-benar menulis after_snapshot ke tabel
 * employees (current state). Dipakai dari dua jalur:
 *  - EmployeeMovementApprovalService::decide() — begitu approval final DAN
 *    effective_date sudah tiba.
 *  - Command employee-movements:apply-due (scheduler) — untuk movement yang
 *    sudah Approved tapi effective_date-nya baru tiba belakangan.
 *
 * Dipisah jadi class sendiri (bukan method di salah satu Service) supaya
 * EmployeeMovementService dan EmployeeMovementApprovalService bisa sama-sama
 * pakai tanpa saling depend satu sama lain (circular dependency).
 */
class EmployeeMovementApplier
{
    public function applyIfDue(EmployeeMovement $movement): void
    {
        if ($movement->status !== EmployeeMovementStatus::Approved) {
            return;
        }

        if ($movement->effective_date->isFuture()) {
            return;
        }

        $this->apply($movement);
    }

    private function apply(EmployeeMovement $movement): void
    {
        $employee = Employee::withTrashed()->find($movement->employee_id);

        if (! $employee) {
            throw new EmployeeMovementException(
                "Employee #{$movement->employee_id} tidak ditemukan saat menerapkan movement #{$movement->id}."
            );
        }

        // Rehire: kalau record-nya sempat di-soft-delete, restore dulu sebelum
        // nulis field lifecycle-nya.
        if ($movement->movement_type === EmployeeMovementType::Rehire && $employee->trashed()) {
            $employee->restore();
        }

        $employee->forceFill($movement->after_snapshot)->save();

        $movement->update([
            'status' => EmployeeMovementStatus::Applied->value,
            'applied_at' => now(),
        ]);
    }
}
