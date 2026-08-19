<?php

namespace App\Modules\CashAdvance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashAdvanceCategory extends Model
{
    protected $fillable = [
        'name',
        'code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function policies(): BelongsToMany
    {
        return $this->belongsToMany(CashAdvancePolicy::class, 'cash_advance_policy_category');
    }

    public function requestItems(): HasMany
    {
        return $this->hasMany(CashAdvanceRequestItem::class, 'cash_advance_category_id');
    }
}