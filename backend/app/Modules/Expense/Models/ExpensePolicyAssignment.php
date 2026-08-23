<?php

namespace App\Modules\Expense\Models;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpensePolicyAssignment extends Model
{
    protected $fillable = [
        'employee_id',
        'expense_policy_id',
        'effective_date',
        'expiration_date',
        'is_active',
        'assigned_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date:Y-m-d',
            'expiration_date' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(ExpensePolicy::class, 'expense_policy_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function isCurrentlyValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $today = now()->toDateString();

        if ($this->effective_date && $this->effective_date->toDateString() > $today) {
            return false;
        }

        if ($this->expiration_date && $this->expiration_date->toDateString() < $today) {
            return false;
        }

        return true;
    }
}