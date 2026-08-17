<?php

namespace App\Modules\Reimbursement\Models;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Reimbursement\Enums\ReimbursementBalanceStatus;
use App\Modules\Reimbursement\Support\ReimbursementMath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReimbursementBalance extends Model
{
    protected $fillable = [
        'employee_id',
        'reimbursement_policy_id',
        'assigned_amount',
        'effective_date',
        'expiration_date',
        'status',
        'stopped_at',
        'stop_reason',
        'assigned_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'assigned_amount' => 'decimal:2',
            'effective_date' => 'date',
            'expiration_date' => 'date',
            'status' => ReimbursementBalanceStatus::class,
            'stopped_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(
            ReimbursementPolicy::class,
            'reimbursement_policy_id'
        );
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'assigned_by_user_id'
        );
    }

    public function transactions(): HasMany
    {
        return $this
            ->hasMany(ReimbursementBalanceTransaction::class)
            ->orderBy('id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ReimbursementRequest::class);
    }

    public function remainingBalance(): ?string
    {
        if ($this->assigned_amount === null) {
            return null;
        }

        $lastTransaction = $this
            ->transactions()
            ->latest('id')
            ->first();

        return $lastTransaction
            ? (string) $lastTransaction->running_balance
            : (string) $this->assigned_amount;
    }

    public function hasSufficientBalance(string $amount): bool
    {
        $remaining = $this->remainingBalance();

        if ($remaining === null) {
            return true;
        }

        return ReimbursementMath::gte($remaining, $amount);
    }

    public function isUsable(): bool
    {
        if ($this->status !== ReimbursementBalanceStatus::Active) {
            return false;
        }

        $today = now()->toDateString();

        if (
            $this->effective_date &&
            $this->effective_date->toDateString() > $today
        ) {
            return false;
        }

        if (
            $this->expiration_date &&
            $this->expiration_date->toDateString() < $today
        ) {
            return false;
        }

        return true;
    }
}