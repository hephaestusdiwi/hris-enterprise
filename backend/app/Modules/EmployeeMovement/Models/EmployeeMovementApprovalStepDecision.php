<?php

namespace App\Modules\EmployeeMovement\Models;

use App\Models\User;
use App\Modules\ApprovalFlow\Models\ApprovalStep;
use App\Modules\EmployeeMovement\Enums\EmployeeMovementApprovalStepDecisionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeMovementApprovalStepDecision extends Model
{
    protected $fillable = [
        'employee_movement_approval_request_id',
        'approval_step_id',
        'sequence',
        'status',
        'decided_by_user_id',
        'notes',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EmployeeMovementApprovalStepDecisionStatus::class,
            'sequence' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(EmployeeMovementApprovalRequest::class, 'employee_movement_approval_request_id');
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
