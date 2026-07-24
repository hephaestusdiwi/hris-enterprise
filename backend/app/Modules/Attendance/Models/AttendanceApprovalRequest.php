<?php

namespace App\Modules\Attendance\Models;

use App\Modules\Attendance\Enums\AttendanceApprovalRequestStatus;
use App\Modules\Attendance\Enums\AttendanceApprovalRequestType;
use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceApprovalRequest extends Model
{
    protected $fillable = [
        'attendance_id',
        'employee_id',
        'approval_flow_id',
        'type',
        'status',
        'current_step_sequence',
        'detected_value',
        'working_value',
        'approved_value',
        'requested_at',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => AttendanceApprovalRequestType::class,
            'status' => AttendanceApprovalRequestStatus::class,
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvalFlow(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlow::class);
    }

    public function stepDecisions(): HasMany
    {
        return $this->hasMany(AttendanceApprovalStepDecision::class)->orderBy('sequence');
    }
}