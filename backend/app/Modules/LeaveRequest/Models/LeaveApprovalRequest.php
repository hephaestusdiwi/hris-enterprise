<?php

namespace App\Modules\LeaveRequest\Models;

use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\Employee\Models\Employee;
use App\Modules\LeaveRequest\Enums\LeaveApprovalRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveApprovalRequest extends Model
{
    protected $fillable = [
        'leave_request_id',
        'employee_id',
        'approval_flow_id',
        'status',
        'current_step_sequence',
        'requested_at',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => LeaveApprovalRequestStatus::class,
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
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
        return $this->hasMany(LeaveApprovalStepDecision::class)->orderBy('sequence');
    }
}