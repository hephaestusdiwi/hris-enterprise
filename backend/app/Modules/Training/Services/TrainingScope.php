<?php

namespace App\Modules\Training\Services;

use App\Models\User;
use App\Modules\Training\Contracts\TrainingScopeInterface;
use App\Modules\Training\Enums\TrainingRecipientType;
use Illuminate\Database\Eloquent\Builder;

class TrainingScope implements TrainingScopeInterface
{
    public function applyMine(Builder $query, User $user): Builder
    {
        $employeeId = $user->employee?->id;
        $roleNames = $user->getRoleNames();

        return $query->where(function (Builder $q) use ($user, $employeeId, $roleNames) {
            if ($employeeId) {
                $q->orWhere('pic_employee_id', $employeeId)
                    ->orWhereHas('sessions.participants', function (Builder $p) use ($employeeId) {
                        $p->where('employee_id', $employeeId);
                    });
            }

            $q->orWhereHas('recipients', function (Builder $r) use ($user, $roleNames) {
                $r->where(function (Builder $r2) use ($user) {
                    $r2->where('recipient_type', TrainingRecipientType::User)
                        ->where('user_id', $user->id);
                })->orWhere(function (Builder $r2) use ($roleNames) {
                    $r2->where('recipient_type', TrainingRecipientType::Role)
                        ->whereIn('role', $roleNames);
                });
            });
        });
    }
}