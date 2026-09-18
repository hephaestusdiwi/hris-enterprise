<?php

namespace App\Modules\Grooming\Policies;

use App\Models\User;

class GroomingStorePolicy
{
    /**
     * Ikut pola yang sudah dipakai EmployeeScope: admin/hr akses semua
     * branch, role lain (mis. Store Leader) cuma boleh untuk branch sendiri.
     * Belum ada tabel multi-branch-access di project ini, jadi ini scoping
     * paling konsisten dengan yang sudah ada tanpa infrastruktur baru.
     */
    public function submitFor(User $user, int $branchId): bool
    {
        if (! $user->can('submit grooming store')) {
            return false;
        }

        if ($user->hasRole(['admin', 'hr'])) {
            return true;
        }

        return $user->employee?->branch_id === $branchId;
    }

    public function viewMonitoring(User $user): bool
    {
        return $user->can('view grooming store');
    }
}