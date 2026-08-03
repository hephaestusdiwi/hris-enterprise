<?php

namespace App\Modules\EmployeeDeduction\Services;

use App\Models\User;
use App\Modules\EmployeeDeduction\Enums\EmployeeDeductionStatus;
use App\Modules\EmployeeDeduction\Exceptions\EmployeeDeductionException;
use App\Modules\EmployeeDeduction\Models\EmployeeDeduction;

class EmployeeDeductionService
{
    public function create(array $data, ?User $actor): EmployeeDeduction
    {
        return EmployeeDeduction::create([
            ...$data,
            'status' => $data['status'] ?? EmployeeDeductionStatus::Draft->value,
            'created_by_user_id' => $actor?->id,
        ]);
    }

    public function update(EmployeeDeduction $deduction, array $data): EmployeeDeduction
    {
        if (! $deduction->isEditable()) {
            throw new EmployeeDeductionException('Deduction dengan status ini tidak dapat diubah. Gunakan Void lalu buat record baru.');
        }

        $deduction->update($data);

        return $deduction->fresh();
    }

    public function void(EmployeeDeduction $deduction, string $reason, User $actor): EmployeeDeduction
    {
        if ($deduction->status === EmployeeDeductionStatus::Void) {
            throw new EmployeeDeductionException('Deduction ini sudah void.');
        }

        if ($deduction->status === EmployeeDeductionStatus::Processed) {
            throw new EmployeeDeductionException('Deduction yang sudah diproses payroll tidak dapat di-void.');
        }

        $deduction->update([
            'status' => EmployeeDeductionStatus::Void->value,
            'voided_at' => now(),
            'voided_by_user_id' => $actor->id,
            'void_reason' => $reason,
        ]);

        return $deduction->fresh();
    }

    public function markReady(EmployeeDeduction $deduction): EmployeeDeduction
    {
        if ($deduction->status !== EmployeeDeductionStatus::Draft) {
            throw new EmployeeDeductionException('Hanya deduction berstatus Draft yang bisa ditandai Ready.');
        }

        $deduction->update(['status' => EmployeeDeductionStatus::Ready->value]);

        return $deduction->fresh();
    }
}