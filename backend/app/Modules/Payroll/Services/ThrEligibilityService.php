<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Models\ThrPolicy;
use App\Modules\Payroll\Support\PayrollMath;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ThrEligibilityService
{
    /**
     * Policy aktif buat company ini per tanggal referensi: yang is_active,
     * effective_date <= referenceDate (atau belum diisi), paling baru duluan.
     */
    public function resolveActivePolicy(int $companyId, CarbonInterface $referenceDate): ?ThrPolicy
    {
        return ThrPolicy::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('effective_date')->orWhereDate('effective_date', '<=', $referenceDate))
            ->orderByRaw('effective_date IS NULL, effective_date DESC')
            ->first();
    }

    /**
     * Masa kerja dalam bulan penuh, dihitung dari join_date sampai
     * referenceDate. Kalau sudah resign sebelum referenceDate, dihitung
     * sampai resign_date (final).
     */
    public function serviceMonths(Employee $employee, CarbonInterface $referenceDate): int
    {
        if (! $employee->join_date) {
            return 0;
        }

        $endDate = $employee->resign_date && $employee->resign_date->lessThan($referenceDate)
            ? $employee->resign_date
            : $referenceDate;

        if ($endDate->lessThan($employee->join_date)) {
            return 0;
        }

        return (int) $employee->join_date->diffInMonths($endDate);
    }

    public function isEligible(Employee $employee, ThrPolicy $policy, CarbonInterface $referenceDate): bool
    {
        return $this->serviceMonths($employee, $referenceDate) >= $policy->minimum_service_months;
    }

    /**
     * Faktor prorata 0..1 — masa kerja penuh (>= full_service_months) dapat
     * THR 100%, di bawah itu prorata linear sesuai Permenaker 6/2016.
     */
    public function prorationFactor(Employee $employee, ThrPolicy $policy, CarbonInterface $referenceDate): string
    {
        $months = min($this->serviceMonths($employee, $referenceDate), $policy->full_service_months);

        if ($policy->full_service_months <= 0) {
            return '1.000000';
        }

        return PayrollMath::div((string) $months, (string) $policy->full_service_months, 6);
    }

    /**
     * @return Collection<int, Employee>
     */
    public function eligibleEmployees(int $companyId, ThrPolicy $policy, CarbonInterface $referenceDate): Collection
    {
        return Employee::query()
            ->where('company_id', $companyId)
            ->where(fn ($q) => $q->whereNull('resign_date')->orWhereDate('resign_date', '>=', $referenceDate))
            ->get()
            ->filter(fn (Employee $employee) => $this->isEligible($employee, $policy, $referenceDate))
            ->values();
    }
}