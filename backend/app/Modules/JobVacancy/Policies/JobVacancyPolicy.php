<?php

namespace App\Modules\JobVacancy\Policies;

use App\Models\User;
use App\Modules\JobVacancy\Models\JobVacancy;

class JobVacancyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view job vacancies');
    }

    public function view(User $user, JobVacancy $jobVacancy): bool
    {
        return $this->isOwner($user, $jobVacancy)
            || $user->can('view job vacancies');
    }

    public function create(User $user): bool
    {
        return $user->can('create job vacancies');
    }

    public function update(User $user, JobVacancy $jobVacancy): bool
    {
        return $this->isOwner($user, $jobVacancy)
            || $user->can('edit job vacancies');
    }

    public function publish(User $user, JobVacancy $jobVacancy): bool
    {
        return $this->isOwner($user, $jobVacancy)
            || $user->can('publish job vacancies');
    }

    public function pause(User $user, JobVacancy $jobVacancy): bool
    {
        return $this->publish($user, $jobVacancy); // reuse: 1 kemampuan "kontrol visibility"
    }

    public function close(User $user, JobVacancy $jobVacancy): bool
    {
        return $this->isOwner($user, $jobVacancy)
            || $user->can('close job vacancies');
    }

    public function cancel(User $user, JobVacancy $jobVacancy): bool
    {
        return $this->isOwner($user, $jobVacancy)
            || $user->can('cancel job vacancies');
    }

    public function archive(User $user, JobVacancy $jobVacancy): bool
    {
        return $this->isOwner($user, $jobVacancy)
            || $user->can('archive job vacancies');
    }

    private function isOwner(User $user, JobVacancy $jobVacancy): bool
    {
        return (bool) $user->employee
            && in_array($user->employee->id, [
                $jobVacancy->hiring_manager_employee_id,
                $jobVacancy->recruiter_employee_id,
            ], true);
    }
}