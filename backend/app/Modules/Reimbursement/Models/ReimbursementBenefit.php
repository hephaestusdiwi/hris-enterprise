<?php

namespace App\Modules\Reimbursement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReimbursementBenefit extends Model
{
    protected $fillable = [
        'reimbursement_policy_id',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(
            ReimbursementPolicy::class,
            'reimbursement_policy_id'
        );
    }
}