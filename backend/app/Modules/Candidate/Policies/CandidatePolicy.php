<?php

namespace App\Modules\Candidate\Policies;

use App\Models\User;
use App\Modules\Candidate\Models\Candidate;

class CandidatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view candidates');
    }

    public function view(User $user, Candidate $candidate): bool
    {
        return $this->isOwner($user, $candidate)
            || $user->can('view candidates');
    }

    private function isOwner(User $user, Candidate $candidate): bool
    {
        $vacancy = $candidate->jobVacancy;

        return (bool) $user->employee
            && $vacancy
            && in_array($user->employee->id, [
                $vacancy->hiring_manager_employee_id,
                $vacancy->recruiter_employee_id,
            ], true);
    }

    public function reconsider(User $user): bool
    {
        return $user->can('reconsider candidates');
    }

    public function select(User $user): bool
    {
        return $user->can('select candidates');
    }

    public function hire(User $user): bool
    {
        return $user->can('hire candidates');
    }
}