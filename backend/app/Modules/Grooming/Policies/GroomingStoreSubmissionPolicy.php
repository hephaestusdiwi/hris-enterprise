<?php

namespace App\Modules\Grooming\Policies;

use App\Models\User;

class GroomingStoreSubmissionPolicy
{
    /**
     * MURNI berbasis permission — TIDAK ADA pengecekan nama role di sini.
     * Default: user dengan 'submit grooming store' cuma boleh assess branch
     * miliknya sendiri (Employee.branch_id). Kalau butuh akses ke SEMUA
     * branch (mis. Area Supervisor), user itu harus dikasih permission
     * TERPISAH 'submit grooming store all branches' lewat Role Management —
     * bukan otomatis didapat cuma karena bernama "admin"/"hr" di seeder.
     * Ini yang memungkinkan pembatasan scope per-store di masa depan tanpa
     * ubah kode: cukup atur permission mana yang di-assign ke role mana.
     */
    public function submitFor(User $user, int $branchId): bool
    {
        if ($user->can('submit grooming store all branches')) {
            return true;
        }

        if (! $user->can('submit grooming store')) {
            return false;
        }

        return $user->employee?->branch_id === $branchId;
    }

    public function viewMonitoring(User $user): bool
    {
        return $user->can('view grooming store');
    }
}