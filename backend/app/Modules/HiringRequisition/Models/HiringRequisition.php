<?php

namespace App\Modules\HiringRequisition\Models;

use App\Modules\Branch\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Department\Models\Department;
use App\Modules\Employee\Models\Employee;
use App\Modules\HiringRequisition\Enums\HiringRequisitionReason;
use App\Modules\HiringRequisition\Enums\HiringRequisitionStatus;
use App\Modules\Position\Models\Position;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class HiringRequisition extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'department_id',
        'position_id',
        'requested_by_employee_id',
        'replacement_for_employee_id',
        'reason',
        'employment_type',
        'headcount_requested',
        'headcount_filled',
        'target_start_date',
        'justification',
        'status',
        'requested_at',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'reason' => HiringRequisitionReason::class,
            'status' => HiringRequisitionStatus::class,
            'headcount_requested' => 'integer',
            'headcount_filled' => 'integer',
            'target_start_date' => 'date',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requested_by_employee_id');
    }

    public function replacementFor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'replacement_for_employee_id');
    }

    public function approvalRequest(): HasOne
    {
        return $this->hasOne(HiringRequisitionApprovalRequest::class);
    }

    public function remainingHeadcount(): int
    {
        return max(0, $this->headcount_requested - $this->headcount_filled);
    }
}