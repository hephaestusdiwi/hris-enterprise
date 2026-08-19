<?php

namespace App\Modules\CashAdvance\Models;

use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\CashAdvance\Enums\CashAdvanceSettlementApprovalRequestStatus;
use App\Modules\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashAdvanceSettlementApprovalRequest extends Model
{
    protected $fillable = [
        'cash_advance_settlement_id',
        'employee_id',
        'approval_flow_id',
        'status',
        'current_step_sequence',
        'requested_at',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CashAdvanceSettlementApprovalRequestStatus::class,
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(CashAdvanceSettlement::class, 'cash_advance_settlement_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvalFlow(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlow::class);
    }

    public function stepDecisions(): HasMany
    {
        return $this->hasMany(CashAdvanceSettlementApprovalStepDecision::class)->orderBy('sequence');
    }
}