<?php

namespace App\Modules\Expense\Models;

use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\Employee\Models\Employee;
use App\Modules\Expense\Enums\ExpenseClaimApprovalRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseClaimApprovalRequest extends Model
{
    protected $fillable = [
        'expense_claim_id',
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
            'status' => ExpenseClaimApprovalRequestStatus::class,
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(ExpenseClaim::class, 'expense_claim_id');
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
        return $this->hasMany(ExpenseClaimApprovalStepDecision::class)->orderBy('sequence');
    }
}