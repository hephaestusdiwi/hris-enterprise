<?php

namespace App\Modules\Expense\Models;

use App\Models\User;
use App\Modules\ApprovalFlow\Models\ApprovalStep;
use App\Modules\Expense\Enums\ExpenseClaimApprovalStepDecisionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseClaimApprovalStepDecision extends Model
{
    protected $fillable = [
        'expense_claim_approval_request_id',
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
            'status' => ExpenseClaimApprovalStepDecisionStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ExpenseClaimApprovalRequest::class, 'expense_claim_approval_request_id');
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