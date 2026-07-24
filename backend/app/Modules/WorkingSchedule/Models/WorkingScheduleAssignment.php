<?php

namespace App\Modules\WorkingSchedule\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkingScheduleAssignment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'working_schedule_id',
        'target_type',
        'target_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function workingSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkingSchedule::class);
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}