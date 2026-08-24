<?php

namespace App\Modules\Expense\Models;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Expense\Enums\ExpenseClaimStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExpenseClaim extends Model
{
    protected $fillable = [
        'employee_id',
        'expense_policy_assignment_id',
        'expense_category_id',
        'expense_subcategory_id',
        'expense_date',
        'amount',
        'description',
        'status',
        'decided_at',
        'cancel_reason',
        'paid_at',
        'paid_by_user_id',
        'payment_note',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date:Y-m-d',
            'amount' => 'decimal:2',
            'status' => ExpenseClaimStatus::class,
            'decided_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function policyAssignment(): BelongsTo
    {
        return $this->belongsTo(ExpensePolicyAssignment::class, 'expense_policy_assignment_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseSubcategory::class, 'expense_subcategory_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ExpenseClaimAttachment::class);
    }

    public function approvalRequest(): HasOne
    {
        return $this->hasOne(ExpenseClaimApprovalRequest::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }
}