<?php

namespace App\Modules\Payroll\Models;

use App\Modules\Company\Models\Company;
use App\Modules\SalaryComponent\Models\SalaryComponent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyPayrollAttendanceSetting extends Model
{
    protected $fillable = [
        'company_id', 'enable_attendance_integration',
        'overtime_hourly_divisor', 'overtime_multiplier_first_hour', 'overtime_multiplier_next_hours',
        'overtime_salary_component_id',
        'late_deduction_per_minute', 'late_deduction_salary_component_id',
    ];

    protected function casts(): array
    {
        return [
            'enable_attendance_integration' => 'boolean',
            'overtime_hourly_divisor' => 'integer',
            'overtime_multiplier_first_hour' => 'decimal:2',
            'overtime_multiplier_next_hours' => 'decimal:2',
            'late_deduction_per_minute' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function overtimeSalaryComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'overtime_salary_component_id');
    }

    public function lateDeductionSalaryComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'late_deduction_salary_component_id');
    }
}