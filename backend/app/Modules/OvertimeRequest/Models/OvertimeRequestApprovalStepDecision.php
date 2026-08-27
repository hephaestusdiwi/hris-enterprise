<?php

namespace App\Modules\OvertimeRequest\Models;

use App\Models\User;
use App\Modules\ApprovalFlow\Models\ApprovalStep;
use App\Modules\OvertimeRequest\Enums\OvertimeRequestApprovalStepDecisionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeRequestApprovalStepDecision extends Model
{
    protected $fillable = [
        'overtime_request_approval_request_id',
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
            'status' => OvertimeRequestApprovalStepDecisionStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(OvertimeRequestApprovalRequest::class, 'overtime_request_approval_request_id');
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