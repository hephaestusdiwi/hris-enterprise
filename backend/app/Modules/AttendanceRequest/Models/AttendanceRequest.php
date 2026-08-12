<?php

namespace App\Modules\AttendanceRequest\Models;

use App\Modules\Attendance\Models\Attendance;
use App\Modules\AttendanceRequest\Enums\AttendanceRequestStatus;
use App\Modules\Employee\Models\Employee;
use App\Modules\Shift\Models\Shift;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_id',
        'attendance_id',
        'attendance_date',
        'shift_id',
        'requested_clock_in',
        'requested_clock_out',
        'reason',
        'status',
        'submitted_at',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'requested_clock_in' => 'datetime',
            'requested_clock_out' => 'datetime',
            'status' => AttendanceRequestStatus::class,
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AttendanceRequestAttachment::class);
    }

    public function approvalRequest(): HasOne
    {
        return $this->hasOne(AttendanceRequestApprovalRequest::class);
    }
}
