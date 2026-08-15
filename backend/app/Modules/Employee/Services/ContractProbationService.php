<?php

namespace App\Modules\Employee\Services;

use App\Models\User;
use App\Modules\Employee\Contracts\ContractProbationServiceInterface;
use App\Modules\Employee\Contracts\EmployeeScopeInterface;
use App\Modules\Employee\Models\Employee;
use App\Modules\ContractProbationSetting\Models\ContractProbationSetting;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Reuse EmployeeScope yang sudah ada (Phase Hierarchy) untuk visibility —
 * TIDAK ada resolver hierarchy baru di sini. Kalau $actor cuma boleh lihat
 * employee tertentu lewat EmployeeScope, daftar Contract & Probation-nya
 * otomatis ikut ter-scope sama persis.
 */
class ContractProbationService implements ContractProbationServiceInterface
{
    public function __construct(private EmployeeScopeInterface $employeeScope)
    {
    }

    public function upcoming(User $actor, ?int $contractThresholdDays = null, ?int $probationThresholdDays = null): Collection
    {
        $query = $this->employeeScope->apply($this->baseQuery(), $actor);

        return $this->computeFromQuery($query, $contractThresholdDays, $probationThresholdDays);
    }

    public function upcomingUnscoped(?int $contractThresholdDays = null, ?int $probationThresholdDays = null): Collection
    {
        return $this->computeFromQuery($this->baseQuery(), $contractThresholdDays, $probationThresholdDays);
    }

    private function baseQuery(): Builder
    {
        return Employee::query()
            ->whereNull('resign_date')
            ->where(function ($query) {
                $query->whereNotNull('contract_end_date')->orWhereNotNull('probation_end_date');
            })
            ->with(['manager', 'employmentType', 'employmentStatus', 'position']);
    }

    private function computeFromQuery(Builder $query, ?int $contractThresholdDays, ?int $probationThresholdDays): Collection
    {
        if ($contractThresholdDays === null || $probationThresholdDays === null) {
            $setting = ContractProbationSetting::current();
            $contractThresholdDays ??= $setting->contract_reminder_days;
            $probationThresholdDays ??= $setting->probation_reminder_days;
        }

        $today = CarbonImmutable::today();
        $results = new Collection();

        foreach ($query->get() as $employee) {
            if ($employee->contract_end_date) {
                $item = $this->buildItemIfEligible('contract', $employee, $employee->contract_end_date, $today, $contractThresholdDays);
                if ($item) {
                    $results->push($item);
                }
            }

            if ($employee->probation_end_date) {
                $item = $this->buildItemIfEligible('probation', $employee, $employee->probation_end_date, $today, $probationThresholdDays);
                if ($item) {
                    $results->push($item);
                }
            }
        }

        return $results->sortBy('remaining_days')->values();
    }

    /**
     * @return array{type: string, employee: Employee, end_date: CarbonImmutable, remaining_days: int}|null
     */
    private function buildItemIfEligible(string $type, Employee $employee, $rawEndDate, CarbonImmutable $today, int $thresholdDays): ?array
    {
        $endDate = CarbonImmutable::parse($rawEndDate)->startOfDay();

        // expiry < today -> sudah lewat, BUKAN "upcoming". Contract/probation
        // yang sudah expired tetap TIDAK otomatis jadi resigned/terminated —
        // itu tanggung jawab business action di Phase berikutnya, bukan di sini.
        if ($endDate->lt($today)) {
            return null;
        }

        // expiry >= today, sekarang aman hitung selisih absolut (tidak ada
        // ambiguitas tanda karena urutannya sudah dipastikan di atas).
        $remainingDays = (int) $today->diffInDays($endDate);

        if ($remainingDays > $thresholdDays) {
            return null;
        }

        return [
            'type' => $type,
            'employee' => $employee,
            'end_date' => $endDate,
            'remaining_days' => $remainingDays,
        ];
    }
}
