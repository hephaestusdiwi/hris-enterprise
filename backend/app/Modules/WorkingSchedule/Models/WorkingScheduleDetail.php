<?php

namespace App\Modules\WorkingSchedule\Models;

use App\Modules\Shift\Models\Shift;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkingScheduleDetail extends Model
{
    protected $fillable = [
        'working_schedule_id',
        'day_of_week',
        'shift_id',
    ];

    public function workingSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkingSchedule::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}