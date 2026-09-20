<?php

namespace App\Modules\Payroll\Services;

use App\Models\User;
use App\Modules\Payroll\Enums\EmployeeNonRegularInputStatus;
use App\Modules\Payroll\Exceptions\EmployeeNonRegularInputException;
use App\Modules\Payroll\Models\EmployeeNonRegularInput;

class EmployeeNonRegularInputService
{
    /**
     * Cegah duplicate processing: employee yang sama + component yang sama +
     * periode yang sama tidak boleh punya lebih dari satu input yang masih
     * "hidup" (draft/ready/processed). Void tidak dihitung — record yang
     * di-void dianggap batal, boleh dibuatkan penggantinya.
     */
    private function assertNoDuplicate(array $data, ?EmployeeNonRegularInput $ignore = null): void
    {
        $exists = EmployeeNonRegularInput::query()
            ->where('employee_id', $data['employee_id'])
            ->where('non_regular_payroll_component_id', $data['non_regular_payroll_component_id'])
            ->where('payroll_period_year', $data['payroll_period_year'])
            ->where('payroll_period_month', $data['payroll_period_month'])
            ->where('status', '!=', EmployeeNonRegularInputStatus::Void->value)
            ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore->id))
            ->exists();

        if ($exists) {
            throw new EmployeeNonRegularInputException(
                'Employee ini sudah punya input non-reguler untuk component & periode yang sama. Void dulu input lama sebelum membuat yang baru.'
            );
        }
    }

    public function create(array $data, ?User $actor): EmployeeNonRegularInput
    {
        $this->assertNoDuplicate($data);

        return EmployeeNonRegularInput::create([
            ...$data,
            'status' => $data['status'] ?? EmployeeNonRegularInputStatus::Draft->value,
            'created_by_user_id' => $actor?->id,
        ]);
    }

    public function update(EmployeeNonRegularInput $input, array $data): EmployeeNonRegularInput
    {
        if (! $input->isEditable()) {
            throw new EmployeeNonRegularInputException('Input dengan status ini tidak dapat diubah. Gunakan Void lalu buat record baru.');
        }

        $this->assertNoDuplicate([...$input->toArray(), ...$data], $input);

        $input->update($data);

        return $input->fresh();
    }

    public function void(EmployeeNonRegularInput $input, string $reason, User $actor): EmployeeNonRegularInput
    {
        if ($input->status === EmployeeNonRegularInputStatus::Void) {
            throw new EmployeeNonRegularInputException('Input ini sudah void.');
        }

        if ($input->status === EmployeeNonRegularInputStatus::Processed) {
            throw new EmployeeNonRegularInputException('Input yang sudah diproses payroll tidak dapat di-void.');
        }

        $input->update([
            'status' => EmployeeNonRegularInputStatus::Void->value,
            'voided_at' => now(),
            'voided_by_user_id' => $actor->id,
            'void_reason' => $reason,
        ]);

        return $input->fresh();
    }

    public function markReady(EmployeeNonRegularInput $input): EmployeeNonRegularInput
    {
        if ($input->status !== EmployeeNonRegularInputStatus::Draft) {
            throw new EmployeeNonRegularInputException('Hanya input berstatus Draft yang bisa ditandai Ready.');
        }

        $input->update(['status' => EmployeeNonRegularInputStatus::Ready->value]);

        return $input->fresh();
    }
}
