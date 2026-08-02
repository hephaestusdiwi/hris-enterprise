<?php

namespace App\Modules\EmployeeAllowance\Services;

use App\Models\User;
use App\Modules\EmployeeAllowance\Enums\EmployeeAllowanceStatus;
use App\Modules\EmployeeAllowance\Exceptions\EmployeeAllowanceException;
use App\Modules\EmployeeAllowance\Models\EmployeeAllowance;

class EmployeeAllowanceService
{
    public function create(array $data, ?User $actor): EmployeeAllowance
    {
        return EmployeeAllowance::create([
            ...$data,
            'status' => $data['status'] ?? EmployeeAllowanceStatus::Draft->value,
            'created_by_user_id' => $actor?->id,
        ]);
    }

    public function update(EmployeeAllowance $allowance, array $data): EmployeeAllowance
    {
        if (! $allowance->isEditable()) {
            throw new EmployeeAllowanceException('Allowance dengan status ini tidak dapat diubah. Gunakan Void lalu buat record baru.');
        }

        $allowance->update($data);

        return $allowance->fresh();
    }

    public function void(EmployeeAllowance $allowance, string $reason, User $actor): EmployeeAllowance
    {
        if ($allowance->status === EmployeeAllowanceStatus::Void) {
            throw new EmployeeAllowanceException('Allowance ini sudah void.');
        }

        if ($allowance->status === EmployeeAllowanceStatus::Processed) {
            throw new EmployeeAllowanceException('Allowance yang sudah diproses payroll tidak dapat di-void.');
        }

        $allowance->update([
            'status' => EmployeeAllowanceStatus::Void->value,
            'voided_at' => now(),
            'voided_by_user_id' => $actor->id,
            'void_reason' => $reason,
        ]);

        return $allowance->fresh();
    }

    public function markReady(EmployeeAllowance $allowance): EmployeeAllowance
    {
        if ($allowance->status !== EmployeeAllowanceStatus::Draft) {
            throw new EmployeeAllowanceException('Hanya allowance berstatus Draft yang bisa ditandai Ready.');
        }

        $allowance->update(['status' => EmployeeAllowanceStatus::Ready->value]);

        return $allowance->fresh();
    }
}