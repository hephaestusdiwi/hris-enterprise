<?php

namespace App\Modules\Attendance\Models;

use App\Modules\Attendance\Enums\AttendanceStatus;
use App\Modules\Branch\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\Shift\Models\Shift;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'attendance_date',
        'shift_id',
        'clock_in',
        'clock_in_latitude',
        'clock_in_longitude',
        'clock_in_distance_meters',
        'clock_in_method',
        'clock_in_device_id',
        'clock_in_branch_id',
        'clock_in_company_id',
        'clock_out',
        'clock_out_latitude',
        'clock_out_longitude',
        'clock_out_distance_meters',
        'clock_out_method',
        'clock_out_device_id',
        'clock_out_branch_id',
        'clock_out_company_id',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'clock_in' => 'datetime',
            'clock_in_latitude' => 'decimal:7',
            'clock_in_longitude' => 'decimal:7',
            'clock_out' => 'datetime',
            'clock_out_latitude' => 'decimal:7',
            'clock_out_longitude' => 'decimal:7',
            'status' => AttendanceStatus::class,
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function clockInDevice(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'clock_in_device_id');
    }

    public function clockOutDevice(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'clock_out_device_id');
    }

    public function clockInBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'clock_in_branch_id');
    }

    public function clockOutBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'clock_out_branch_id');
    }

    public function clockInCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'clock_in_company_id');
    }

    public function clockOutCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'clock_out_company_id');
    }
}