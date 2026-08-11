<?php

namespace App\Modules\Interview\Policies;

use App\Models\User;
use App\Modules\Interview\Models\Interview;

class InterviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view interviews');
    }

    public function view(User $user, Interview $interview): bool
    {
        return $this->isInterviewer($user, $interview)
            || $user->can('view interviews');
    }

    public function create(User $user): bool
    {
        return $user->can('schedule interviews');
    }

    public function start(User $user, Interview $interview): bool
    {
        return $this->isInterviewer($user, $interview)
            || $user->can('conduct interviews');
    }

    public function complete(User $user, Interview $interview): bool
    {
        return $this->isInterviewer($user, $interview)
            || $user->can('conduct interviews');
    }

    public function cancel(User $user, Interview $interview): bool
    {
        return $this->isInterviewer($user, $interview)
            || $user->can('schedule interviews');
    }

    private function isInterviewer(User $user, Interview $interview): bool
    {
        return (bool) $user->employee
            && $user->employee->id === $interview->interviewer_employee_id;
    }
}