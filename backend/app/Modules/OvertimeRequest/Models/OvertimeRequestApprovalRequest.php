<?php

namespace App\Modules\OvertimeRequest\Models;

use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\Employee\Models\Employee;
use App\Modules\OvertimeRequest\Enums\OvertimeRequestApprovalRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OvertimeRequestApprovalRequest extends Model
{
    protected $fillable = [
        'overtime_request_id',
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
            'status' => OvertimeRequestApprovalRequestStatus::class,
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function overtimeRequest(): BelongsTo
    {
        return $this->belongsTo(OvertimeRequest::class);
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
        return $this->hasMany(OvertimeRequestApprovalStepDecision::class)->orderBy('sequence');
    }
}