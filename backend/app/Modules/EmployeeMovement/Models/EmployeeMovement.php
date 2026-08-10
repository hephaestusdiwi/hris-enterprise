<?php

namespace App\Modules\EmployeeMovement\Models;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\EmployeeMovement\Enums\EmployeeMovementStatus;
use App\Modules\EmployeeMovement\Enums\EmployeeMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmployeeMovement extends Model
{
    protected $fillable = [
        'employee_id',
        'movement_type',
        'effective_date',
        'status',
        'before_snapshot',
        'after_snapshot',
        'reason',
        'requested_by_user_id',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'movement_type' => EmployeeMovementType::class,
            'status' => EmployeeMovementStatus::class,
            'effective_date' => 'date',
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'applied_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approvalRequest(): HasOne
    {
        return $this->hasOne(EmployeeMovementApprovalRequest::class);
    }
}
