<?php

namespace App\Modules\Employee\Contracts;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Deteksi employee yang contract/probation-nya mendekati akhir.
 *
 * PENTING: ini murni monitoring/alert di atas data Employee — BUKAN source
 * of truth baru. Tidak menyimpan apapun, tidak mengubah apapun, cuma
 * membaca contract_end_date/probation_end_date yang sudah ada.
 */
interface ContractProbationServiceInterface
{
    /**
     * @return Collection<int, array{
     *   type: 'contract'|'probation',
     *   employee: \App\Modules\Employee\Models\Employee,
     *   end_date: \Carbon\CarbonImmutable,
     *   remaining_days: int,
     * }>
     */
    public function upcoming(User $actor, ?int $contractThresholdDays = null, ?int $probationThresholdDays = null): Collection;

    /**
     * SAMA seperti upcoming(), TAPI tanpa EmployeeScope — dipakai KHUSUS
     * konteks system/batch (scheduler) yang butuh tahu SEMUA employee
     * company-wide buat menentukan siapa yang perlu dikirimi reminder.
     * JANGAN dipanggil dari HTTP request/controller — itu tetap harus
     * lewat upcoming() yang di-scope.
     *
     * @return Collection<int, array{type: 'contract'|'probation', employee: \App\Modules\Employee\Models\Employee, end_date: \Carbon\CarbonImmutable, remaining_days: int}>
     */
    public function upcomingUnscoped(?int $contractThresholdDays = null, ?int $probationThresholdDays = null): Collection;
}
