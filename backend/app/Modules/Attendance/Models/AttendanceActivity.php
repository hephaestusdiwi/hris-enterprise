<?php

namespace App\Modules\Attendance\Models;

use App\Models\User;
use App\Modules\Attendance\Enums\AttendanceActivityType;
use App\Modules\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceActivity extends Model
{
    protected $fillable = [
        'employee_id',
        'attendance_id',
        'activity_type',
        'actor_user_id',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'activity_type' => AttendanceActivityType::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}