<?php

namespace App\Modules\Expense\Models;

use App\Models\User;
use App\Modules\Company\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpensePolicy extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'description',
        'effective_date',
        'expiration_date',
        'is_active',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'expiration_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ExpenseCategory::class, 'expense_policy_category')
            ->withPivot('limit_amount')
            ->withTimestamps();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ExpensePolicyAssignment::class);
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

        if ($this->effective_date && $this->effective_date->toDateString() > $today) {
            return false;
        }

        if ($this->expiration_date && $this->expiration_date->toDateString() < $today) {
            return false;
        }

        return true;
    }
}