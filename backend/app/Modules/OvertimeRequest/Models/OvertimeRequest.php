<?php

namespace App\Modules\OvertimeRequest\Models;

use App\Modules\Attendance\Models\Attendance;
use App\Modules\Employee\Models\Employee;
use App\Modules\OvertimeRequest\Enums\OvertimeRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class OvertimeRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_id',
        'attendance_date',
        'planned_minutes',
        'reason',
        'status',
        'requested_at',
        'decided_at',
        'attendance_id',
        'actual_overtime_minutes',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'status' => OvertimeRequestStatus::class,
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
            'claimed_at' => 'datetime',
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

    public function approvalRequest(): HasOne
    {
        return $this->hasOne(OvertimeRequestApprovalRequest::class);
    }
}