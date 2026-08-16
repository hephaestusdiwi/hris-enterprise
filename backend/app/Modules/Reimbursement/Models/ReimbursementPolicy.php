<?php

namespace App\Modules\Reimbursement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReimbursementPolicy extends Model
{
    protected $fillable = [
        'name',
        'description',
        'effective_date',
        'expiration_date',
        'default_limit_amount',
        'is_active',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'expiration_date' => 'date',
            'default_limit_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function benefits(): HasMany
    {
        return $this->hasMany(ReimbursementBenefit::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(ReimbursementBalance::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isCurrentlyEffective(): bool
    {
        if (! $this->is_active) {
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