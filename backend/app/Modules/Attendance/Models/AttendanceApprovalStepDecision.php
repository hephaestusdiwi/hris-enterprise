<?php

namespace App\Modules\Attendance\Models;

use App\Models\User;
use App\Modules\ApprovalFlow\Models\ApprovalStep;
use App\Modules\Attendance\Enums\AttendanceApprovalStepDecisionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceApprovalStepDecision extends Model
{
    protected $fillable = [
        'attendance_approval_request_id',
        'approval_step_id',
        'sequence',
        'status',
        'decided_by_user_id',
        'adjusted_value',
        'notes',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttendanceApprovalStepDecisionStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(AttendanceApprovalRequest::class, 'attendance_approval_request_id');
    }

    public function approvalStep(): BelongsTo
    {
        return $this->belongsTo(ApprovalStep::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}