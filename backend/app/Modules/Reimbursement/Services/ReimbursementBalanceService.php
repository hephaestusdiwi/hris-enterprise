<?php

namespace App\Modules\Reimbursement\Services;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Reimbursement\Enums\ReimbursementBalanceStatus;
use App\Modules\Reimbursement\Enums\ReimbursementBalanceTransactionType;
use App\Modules\Reimbursement\Exceptions\ReimbursementValidationException;
use App\Modules\Reimbursement\Models\ReimbursementBalance;
use App\Modules\Reimbursement\Models\ReimbursementBalanceTransaction;
use App\Modules\Reimbursement\Models\ReimbursementPolicy;
use Illuminate\Support\Facades\DB;

class ReimbursementBalanceService
{
    /**
     * @param array{
     *     assigned_amount?: string|float|null,
     *     effective_date: string,
     *     expiration_date?: ?string
     * } $data
     */
    public function assign(
        Employee $employee,
        ReimbursementPolicy $policy,
        array $data,
        ?User $actor
    ): ReimbursementBalance {
        if (! $policy->is_active) {
            throw new ReimbursementValidationException(
                'Policy ini sudah tidak aktif.'
            );
        }

        $existingActive = ReimbursementBalance::where(
            'employee_id',
            $employee->id
        )
            ->where(
                'reimbursement_policy_id',
                $policy->id
            )
            ->where(
                'status',
                ReimbursementBalanceStatus::Active->value
            )
            ->exists();

        if ($existingActive) {
            throw new ReimbursementValidationException(
                'Employee ini sudah punya balance Active untuk policy yang sama. Stop dulu yang lama kalau mau assign ulang.'
            );
        }

        $assignedAmount =
            array_key_exists('assigned_amount', $data) &&
            $data['assigned_amount'] !== null
                ? (string) $data['assigned_amount']
                : (
                    $policy->default_limit_amount !== null
                        ? (string) $policy->default_limit_amount
                        : null
                );

        return DB::transaction(
            function () use (
                $employee,
                $policy,
                $data,
                $actor,
                $assignedAmount
            ) {
                $balance = ReimbursementBalance::create([
                    'employee_id' => $employee->id,
                    'reimbursement_policy_id' => $policy->id,
                    'assigned_amount' => $assignedAmount,
                    'effective_date' => $data['effective_date'],
                    'expiration_date' =>
                        $data['expiration_date']
                        ?? $policy->expiration_date,
                    'status' =>
                        ReimbursementBalanceStatus::Active->value,
                    'assigned_by_user_id' => $actor?->id,
                ]);

                if ($assignedAmount !== null) {
                    ReimbursementBalanceTransaction::create([
                        'reimbursement_balance_id' => $balance->id,
                        'type' =>
                            ReimbursementBalanceTransactionType::Initial->value,
                        'amount' => $assignedAmount,
                        'running_balance' => $assignedAmount,
                        'note' => 'Assign awal.',
                    ]);
                }

                return $balance->fresh();
            }
        );
    }

    public function stop(
        ReimbursementBalance $balance,
        string $reason
    ): ReimbursementBalance {
        if (
            $balance->status !==
                ReimbursementBalanceStatus::Active
        ) {
            throw new ReimbursementValidationException(
                'Balance ini sudah tidak Active.'
            );
        }

        $balance->update([
            'status' =>
                ReimbursementBalanceStatus::Stopped->value,
            'stopped_at' => now(),
            'stop_reason' => $reason,
        ]);

        return $balance->fresh();
    }
}