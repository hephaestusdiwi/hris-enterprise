<?php
 
namespace App\Modules\Training\Policies;
 
use App\Models\User;
use App\Modules\Training\Enums\TrainingRecipientType;
use App\Modules\Training\Models\TrainingProgram;

class TrainingProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view trainings');
    }

    public function view(User $user, TrainingProgram $program): bool
    {
        if ($user->can('view trainings')) {
            return true;
        }

        if ($this->isPic($user, $program)) {
            return true;
        }

        if ($this->isParticipant($user, $program)) {
            return true;
        }

        return $program->recipients
            ->where('recipient_type', TrainingRecipientType::User)
            ->contains('user_id', $user->id);
    }

    public function create(User $user): bool
    {
        return $user->can('create trainings');
    }

    public function update(User $user, TrainingProgram $program): bool
    {
        return $user->can('edit trainings');
    }

    public function delete(User $user, TrainingProgram $program): bool
    {
        return $user->can('delete trainings');
    }

    private function isPic(User $user, TrainingProgram $program): bool
    {
        return (bool) $user->employee
            && $program->pic_employee_id !== null
            && $user->employee->id === $program->pic_employee_id;
    }

    private function isParticipant(User $user, TraningProgram $program): bool
    {
        if (! $user->employee) {
            return false;
        }

        return $program->participants()
            ->where('employee_id', $user->employee->id)
            ->exists();
    }
}