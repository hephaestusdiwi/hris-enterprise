<?php

namespace App\Modules\LeaveBalance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalanceAdjustment extends Model
{
    protected $fillable = [
        'leave_balance_id',
        'adjustment_days',
        'reason',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'adjustment_days' => 'decimal:2',
        ];
    }

    public function leaveBalance(): BelongsTo
    {
        return $this->belongsTo(LeaveBalance::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}