<?php

namespace App\Modules\EmployeeMovement\Services;

use App\Modules\Employee\Models\Employee;
use App\Modules\EmployeeMovement\Enums\EmployeeMovementStatus;
use App\Modules\EmployeeMovement\Enums\EmployeeMovementType;
use App\Modules\EmployeeMovement\Exceptions\EmployeeMovementException;
use App\Modules\EmployeeMovement\Models\EmployeeMovement;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class EmployeeMovementService
{
    public function __construct(
        private EmployeeMovementApprovalService $approvalService,
        private EmployeeMovementApplier $applier,
    ) {
    }

    /**
     * @param  array<string, mixed>  $afterValues  Field lifecycle baru sesuai movement_type (lihat EmployeeMovementType::relevantFields())
     */
    public function create(
        Employee $employee,
        EmployeeMovementType $type,
        Carbon $effectiveDate,
        array $afterValues,
        int $requestedByUserId,
        ?string $reason,
    ): EmployeeMovement {
        if ($type === EmployeeMovementType::Rehire && ! $employee->trashed() && $employee->resign_date === null) {
            throw new EmployeeMovementException('Rehire hanya berlaku untuk employee yang statusnya sudah resign.');
        }

        if ($type !== EmployeeMovementType::Rehire && $employee->trashed()) {
            throw new EmployeeMovementException('Employee ini sudah tidak aktif (soft-deleted). Gunakan movement Rehire untuk mengaktifkan kembali.');
        }

        $fields = $type->relevantFields();
        $beforeSnapshot = collect($fields)->mapWithKeys(fn (string $field) => [$field => $this->normalizeValue($employee->{$field})])->all();
        $afterSnapshot = collect($fields)->mapWithKeys(fn (string $field) => [$field => $this->normalizeValue($afterValues[$field] ?? null)])->all();

        return DB::transaction(function () use ($employee, $type, $effectiveDate, $beforeSnapshot, $afterSnapshot, $requestedByUserId, $reason) {
            $movement = EmployeeMovement::create([
                'employee_id' => $employee->id,
                'movement_type' => $type->value,
                'effective_date' => $effectiveDate->toDateString(),
                'status' => EmployeeMovementStatus::PendingApproval->value,
                'before_snapshot' => $beforeSnapshot,
                'after_snapshot' => $afterSnapshot,
                'reason' => $reason,
                'requested_by_user_id' => $requestedByUserId,
            ]);

            // Bisa throw EmployeeMovementException kalau tidak ada ApprovalFlow yang
            // berlaku — movement TIDAK dibuat (transaction rollback), sesuai preseden
            // HiringRequisition: diblokir, bukan auto-approve.
            $this->approvalService->initiate($movement, $employee);

            return $movement->fresh();
        });
    }

    public function cancel(EmployeeMovement $movement): EmployeeMovement
    {
        if (! in_array($movement->status, [EmployeeMovementStatus::PendingApproval, EmployeeMovementStatus::Approved], true)) {
            throw new EmployeeMovementException('Movement dengan status ini tidak bisa dibatalkan.');
        }

        $this->approvalService->cancelApprovalIfAny($movement);

        $movement->update(['status' => EmployeeMovementStatus::Cancelled->value]);

        return $movement->fresh();
    }

    /**
     * Dipanggil dari command employee-movements:apply-due (scheduler harian).
     * Movement yang statusnya sudah Approved tapi effective_date-nya baru
     * tiba hari ini (waktu approval masih di masa depan).
     */
    public function applyDueMovements(): int
    {
        $movements = EmployeeMovement::where('status', EmployeeMovementStatus::Approved->value)
            ->whereDate('effective_date', '<=', Carbon::today())
            ->orderBy('effective_date')
            ->orderBy('id')
            ->get();

        foreach ($movements as $movement) {
            $this->applier->applyIfDue($movement);
        }

        return $movements->count();
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        return $value;
    }
}
