<?php

namespace App\Modules\EmployeeMovement\Models;

use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\Employee\Models\Employee;
use App\Modules\EmployeeMovement\Enums\EmployeeMovementApprovalRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeMovementApprovalRequest extends Model
{
    protected $fillable = [
        'employee_movement_id',
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
            'status' => EmployeeMovementApprovalRequestStatus::class,
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function employeeMovement(): BelongsTo
    {
        return $this->belongsTo(EmployeeMovement::class);
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
        return $this->hasMany(EmployeeMovementApprovalStepDecision::class)->orderBy('sequence');
    }
}
