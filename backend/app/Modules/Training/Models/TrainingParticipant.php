<?php

namespace App\Modules\Training\Models;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Training\Enums\TrainingParticipantStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingParticipant extends Model
{
    protected $fillable = [
        'training_session_id',
        'employee_id',
        'status',
        'score',
        'notes',
        'registered_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => TrainingParticipantStatus::class,
            'score' => 'decimal:2',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }

    public function isActive(): bool
    {
        return $this->status !== TrainingParticipantStatus::Cancelled;
    }
}