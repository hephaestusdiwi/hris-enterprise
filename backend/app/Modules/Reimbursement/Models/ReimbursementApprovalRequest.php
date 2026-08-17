<?php

namespace App\Modules\Reimbursement\Models;

use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\Employee\Models\Employee;
use App\Modules\Reimbursement\Enums\ReimbursementApprovalRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReimbursementApprovalRequest extends Model
{
    protected $fillable = [
        'reimbursement_request_id',
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
            'status' => ReimbursementApprovalRequestStatus::class,
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(
            ReimbursementRequest::class,
            'reimbursement_request_id'
        );
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
        return $this
            ->hasMany(ReimbursementApprovalStepDecision::class)
            ->orderBy('sequence');
    }
}