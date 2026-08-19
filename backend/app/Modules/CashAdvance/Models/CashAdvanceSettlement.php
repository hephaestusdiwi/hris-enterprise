<?php

namespace App\Modules\CashAdvance\Models;

use App\Models\User;
use App\Modules\CashAdvance\Enums\CashAdvanceSettlementStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CashAdvanceSettlement extends Model
{
    protected $fillable = [
        'cash_advance_request_id',
        'total_actual_amount',
        'total_returned_amount',
        'notes',
        'status',
        'submitted_at',
        'rejected_at',
        'approved_at',
        'verified_at',
        'verified_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'total_actual_amount' => 'decimal:2',
            'total_returned_amount' => 'decimal:2',
            'status' => CashAdvanceSettlementStatus::class,
            'submitted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'approved_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(CashAdvanceRequest::class, 'cash_advance_request_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CashAdvanceSettlementItem::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CashAdvanceSettlementAttachment::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function approvalRequest(): HasOne
    {
        return $this->hasOne(CashAdvanceSettlementApprovalRequest::class);
    }
}