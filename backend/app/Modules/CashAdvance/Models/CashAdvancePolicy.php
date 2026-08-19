<?php

namespace App\Modules\CashAdvance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashAdvancePolicy extends Model
{
    protected $fillable = [
        'name',
        'effective_date',
        'settlement_due_days',
        'is_active',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'settlement_due_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(CashAdvanceCategory::class, 'cash_advance_policy_category');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(CashAdvanceRequest::class);
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

        return $this->effective_date === null || $this->effective_date->toDateString() <= now()->toDateString();
    }
}