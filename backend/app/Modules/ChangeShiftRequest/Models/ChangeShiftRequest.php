<?php

namespace App\Modules\ChangeShiftRequest\Models;

use App\Modules\ChangeShiftRequest\Enums\ChangeShiftRequestStatus;
use App\Modules\Employee\Models\Employee;
use App\Modules\Shift\Models\Shift;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChangeShiftRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_id',
        'attendance_date',
        'current_shift_id',
        'requested_shift_id',
        'reason',
        'status',
        'requested_at',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'status' => ChangeShiftRequestStatus::class,
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function currentShift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'current_shift_id');
    }

    public function requestedShift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'requested_shift_id');
    }

    public function approvalRequest(): HasOne
    {
        return $this->hasOne(ChangeShiftRequestApprovalRequest::class);
    }
}