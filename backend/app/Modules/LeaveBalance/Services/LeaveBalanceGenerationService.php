<?php

namespace App\Modules\LeaveBalance\Services;

use App\Modules\Employee\Models\Employee;
use App\Modules\LeaveBalance\Contracts\LeaveQuotaProrationStrategyInterface;
use App\Modules\LeaveBalance\Models\LeaveBalance;
use App\Modules\LeaveType\Models\LeaveType;
use Carbon\Carbon;

class LeaveBalanceGenerationService
{
    public function __construct(
        private LeaveBalanceEligibilityService $eligibilityService,
        private LeaveQuotaProrationStrategyInterface $prorationStrategy,
    ) {
    }

    /**
     * @return array<int, LeaveBalance> balance yang baru dibuat (kosong kalau semua udah ada / gak eligible)
     */
    public function generateForEmployee(Employee $employee, Carbon $referenceDate): array
    {
        $periodStart = Carbon::create($referenceDate->year, 1, 1);
        $periodEnd = Carbon::create($referenceDate->year, 12, 31);

        $leaveTypes = LeaveType::where('company_id', $employee->company_id)
            ->where('is_active', true)
            ->where('requires_balance', true)
            ->get();

        $created = [];

        foreach ($leaveTypes as $leaveType) {
            $exists = LeaveBalance::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('period_start', $periodStart->toDateString())
                ->exists();

            if ($exists) {
                continue;
            }

            if (! $this->eligibilityService->isEligible($employee, $leaveType, $referenceDate)) {
                continue;
            }

            $eligibleFrom = $this->eligibilityService->resolveEligibleFrom($employee, $leaveType);
            $effectiveStart = $eligibleFrom->gt($periodStart) ? $eligibleFrom : $periodStart;

            $initialQuota = $this->prorationStrategy->calculateQuota($leaveType, $periodStart, $periodEnd, $effectiveStart);

            [$carryOverDays, $carryOverExpiryDate] = $this->resolveCarryOver($employee, $leaveType, $periodStart);

            $created[] = LeaveBalance::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'eligible_from' => $eligibleFrom->toDateString(),
                'initial_quota' => $initialQuota,
                'carry_over_days' => $carryOverDays,
                'carry_over_expiry_date' => $carryOverExpiryDate?->toDateString(),
                'used_days' => 0,
                'generated_at' => now(),
            ]);
        }

        return $created;
    }

    /**
     * @return int jumlah balance baru yang berhasil dibuat
     */
    public function generateForAllEmployees(Carbon $referenceDate): int
    {
        $count = 0;

        Employee::query()
            ->where(function ($query) use ($referenceDate) {
                $query->whereNull('resign_date')->orWhere('resign_date', '>=', $referenceDate->toDateString());
            })
            ->chunkById(100, function ($employees) use ($referenceDate, &$count) {
                foreach ($employees as $employee) {
                    $count += count($this->generateForEmployee($employee, $referenceDate));
                }
            });

        return $count;
    }

    /**
     * @return array{0: float, 1: ?Carbon}
     */
    private function resolveCarryOver(Employee $employee, LeaveType $leaveType, Carbon $periodStart): array
    {
        if (! $leaveType->carry_over_allowed) {
            return ['0.00', null];
        }

        $previousPeriodStart = $periodStart->copy()->subYear();

        $previousBalance = LeaveBalance::where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('period_start', $previousPeriodStart->toDateString())
            ->with('adjustments')
            ->first();

        if (! $previousBalance) {
            return ['0.00', null];
        }

        $remainingRaw = $previousBalance->remainingDaysAsString() ?? '0.00';
        $remaining = LeaveBalanceMath::max('0', $remainingRaw);

        $carryOverDays = $leaveType->carry_over_max_days !== null
            ? LeaveBalanceMath::min($remaining, (string) $leaveType->carry_over_max_days)
            : $remaining;

        $carryOverExpiryDate = null;
        if ($leaveType->carry_over_expiry_month) {
            $carryOverExpiryDate = Carbon::create($periodStart->year, $leaveType->carry_over_expiry_month, 1)->endOfMonth();
        }

        return [$carryOverDays, $carryOverExpiryDate];
    }
}