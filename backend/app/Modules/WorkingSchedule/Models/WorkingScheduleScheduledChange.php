<?php

namespace App\Modules\WorkingSchedule\Models;

use App\Models\User;
use App\Modules\WorkingSchedule\Enums\ScheduledChangeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WorkingScheduleScheduledChange extends Model
{
    protected $fillable = [
        'target_type',
        'target_id',
        'working_schedule_id',
        'effective_date',
        'status',
        'created_by_user_id',
        'notes',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'status' => ScheduledChangeStatus::class,
            'applied_at' => 'datetime',
        ];
    }

    public function workingSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkingSchedule::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}