<?php

namespace App\Modules\CashAdvance\Models;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\CashAdvance\Enums\CashAdvanceRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CashAdvanceRequest extends Model
{
    protected $fillable = [
        'employee_id',
        'cash_advance_policy_id',
        'purpose',
        'date_of_use',
        'notes',
        'total_amount',
        'status',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'cancelled_at',
        'cancel_reason',
        'disbursed_at',
        'disbursed_by_user_id',
        'disbursement_note',
    ];

    protected function casts(): array
    {
        return [
            'date_of_use' => 'date',
            'total_amount' => 'decimal:2',
            'status' => CashAdvanceRequestStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'disbursed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(CashAdvancePolicy::class, 'cash_advance_policy_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CashAdvanceRequestItem::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CashAdvanceAttachment::class);
    }

    public function disbursedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disbursed_by_user_id');
    }

    public function approvalRequest(): HasOne
    {
        return $this->hasOne(CashAdvanceApprovalRequest::class);
    }

    /**
     * Histori semua percobaan settlement (termasuk yang rejected) --
     * tidak pernah ditimpa/dihapus. Gunakan latestSettlement() untuk
     * yang aktif/terbaru.
     */
    public function settlements(): HasMany
    {
        return $this->hasMany(CashAdvanceSettlement::class)->orderBy('id');
    }

    public function latestSettlement(): ?CashAdvanceSettlement
    {
        return $this->settlements()->latest('id')->first();
    }
}