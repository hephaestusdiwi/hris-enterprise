<?php

namespace App\Modules\Grooming\Policies;

use App\Models\User;
use App\Modules\Grooming\Models\GroomingSelfSubmission;

class GroomingSelfSubmissionPolicy
{
    /**
     * Submit Grooming Self selalu untuk diri sendiri — siapapun yang punya
     * data Employee boleh, tidak perlu permission khusus.
     */
    public function create(User $user): bool
    {
        return (bool) $user->employee;
    }

    public function viewOwnHistory(User $user): bool
    {
        return (bool) $user->employee;
    }

    public function viewMonitoring(User $user): bool
    {
        return $user->can('view grooming self');
    }

    public function view(User $user, GroomingSelfSubmission $submission): bool
    {
        if ($user->can('view grooming self')) {
            return true;
        }

        return $user->employee?->id === $submission->employee_id;
    }
}