<?php

namespace App\Modules\LeaveRequest\Models;

use App\Modules\Employee\Models\Employee;
use App\Modules\LeaveBalance\Models\LeaveBalance;
use App\Modules\LeaveRequest\Enums\HalfDaySession;
use App\Modules\LeaveRequest\Enums\LeaveRequestSource;
use App\Modules\LeaveRequest\Enums\LeaveRequestStatus;
use App\Modules\LeaveType\Models\LeaveType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'leave_balance_id',
        'start_date',
        'end_date',
        'is_half_day',
        'half_day_session',
        'start_time',
        'end_time',
        'total_days',
        'reason',
        'attachment_path',
        'status',
        'source',
        'requested_at',
        'decided_at',
        'reversed_by_user_id',
        'reversed_at',
        'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_half_day' => 'boolean',
            'half_day_session' => HalfDaySession::class,
            'total_days' => 'decimal:2',
            'status' => LeaveRequestStatus::class,
            'source' => LeaveRequestSource::class,
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function leaveBalance(): BelongsTo
    {
        return $this->belongsTo(LeaveBalance::class);
    }

    public function approvalRequest(): HasOne
    {
        return $this->hasOne(LeaveApprovalRequest::class);
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'reversed_by_user_id');
    }
}