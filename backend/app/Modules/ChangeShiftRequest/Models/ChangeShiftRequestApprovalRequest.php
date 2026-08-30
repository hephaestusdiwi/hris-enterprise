<?php

namespace App\Modules\ChangeShiftRequest\Models;

use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\ChangeShiftRequest\Enums\ChangeShiftRequestApprovalRequestStatus;
use App\Modules\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChangeShiftRequestApprovalRequest extends Model
{
    protected $fillable = [
        'change_shift_request_id',
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
            'status' => ChangeShiftRequestApprovalRequestStatus::class,
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function changeShiftRequest(): BelongsTo
    {
        return $this->belongsTo(ChangeShiftRequest::class);
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
        return $this->hasMany(ChangeShiftRequestApprovalStepDecision::class)->orderBy('sequence');
    }
}