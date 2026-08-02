<?php

namespace App\Modules\EmployeeAllowance\Models;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\EmployeeAllowance\Enums\EmployeeAllowanceStatus;
use App\Modules\SalaryComponent\Models\SalaryComponent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeAllowance extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_id',
        'salary_component_id',
        'payroll_period_year',
        'payroll_period_month',
        'amount',
        'remark',
        'status',
        'created_by_user_id',
        'processed_at',
        'voided_at',
        'voided_by_user_id',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => EmployeeAllowanceStatus::class,
            'processed_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [EmployeeAllowanceStatus::Draft, EmployeeAllowanceStatus::Ready], true);
    }
}