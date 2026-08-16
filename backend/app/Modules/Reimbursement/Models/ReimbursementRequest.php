<?php

namespace App\Modules\Reimbursement\Models;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Reimbursement\Enums\ReimbursementRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReimbursementRequest extends Model
{
    protected $fillable = [
        'employee_id',
        'reimbursement_policy_id',
        'reimbursement_balance_id',
        'transaction_date',
        'total_amount',
        'notes',
        'status',
        'decided_at',
        'disbursed_at',
        'disbursed_by_user_id',
        'disbursement_note',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'total_amount' => 'decimal:2',
            'status' => ReimbursementRequestStatus::class,
            'decided_at' => 'datetime',
            'disbursed_at' => 'datetime',
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

    public function balance(): BelongsTo
    {
        return $this->belongsTo(
            ReimbursementBalance::class,
            'reimbursement_balance_id'
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReimbursementRequestItem::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ReimbursementAttachment::class);
    }

    public function disbursedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disbursed_by_user_id');
    }

    public function approvalRequest(): HasOne
    {
        return $this->hasOne(ReimbursementApprovalRequest::class);
    }
}