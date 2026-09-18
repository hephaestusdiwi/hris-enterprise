<?php

namespace App\Modules\EmployeeReprimand\Services;

use App\Models\User;
use App\Modules\EmployeeReprimand\Enums\EmployeeReprimandStatus;
use App\Modules\EmployeeReprimand\Exceptions\EmployeeReprimandException;
use App\Modules\EmployeeReprimand\Models\EmployeeReprimand;

class EmployeeReprimandService
{
    public function create(array $data, ?User $actor): EmployeeReprimand
    {
        return EmployeeReprimand::create([
            ...$data,
            'status' => EmployeeReprimandStatus::Active->value,
            'created_by_user_id' => $actor?->id,
        ]);
    }

    public function update(EmployeeReprimand $reprimand, array $data): EmployeeReprimand
    {
        if (! $reprimand->isEditable()) {
            throw new EmployeeReprimandException('Reprimand yang sudah di-void tidak dapat diubah. Buat record baru bila diperlukan.');
        }

        $reprimand->update($data);

        return $reprimand->fresh();
    }

    /**
     * "Hapus" reprimand = void, BUKAN hard delete -- reprimand adalah
     * historical/disciplinary record, jejaknya wajib tetap ada meski
     * dibatalkan (mis. salah input, atau keputusan dicabut). Pola persis
     * EmployeeDeductionService::void().
     */
    public function void(EmployeeReprimand $reprimand, string $reason, User $actor): EmployeeReprimand
    {
        if ($reprimand->status === EmployeeReprimandStatus::Void) {
            throw new EmployeeReprimandException('Reprimand ini sudah void.');
        }

        $reprimand->update([
            'status' => EmployeeReprimandStatus::Void->value,
            'voided_at' => now(),
            'voided_by_user_id' => $actor->id,
            'void_reason' => $reason,
        ]);

        return $reprimand->fresh();
    }
}