<?php

namespace App\Modules\Payroll\Models;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Enums\EmployeeNonRegularInputStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeNonRegularInput extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_id',
        'non_regular_payroll_component_id',
        'payroll_period_year',
        'payroll_period_month',
        'amount',
        'is_addition',
        'note',
        'status',
        'created_by_user_id',
        'processed_at',
        'payroll_run_id',
        'voided_at',
        'voided_by_user_id',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_addition' => 'boolean',
            'status' => EmployeeNonRegularInputStatus::class,
            'processed_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(NonRegularPayrollComponent::class, 'non_regular_payroll_component_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [EmployeeNonRegularInputStatus::Draft, EmployeeNonRegularInputStatus::Ready], true);
    }
}