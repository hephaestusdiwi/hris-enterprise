<?php

namespace App\Modules\EmployeeSalary\Models;

use App\Modules\SalaryComponent\Enums\PercentageBase;
use App\Modules\SalaryComponent\Models\SalaryComponent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalaryOverride extends Model
{
    protected $fillable = [
        'employee_salary_id',
        'salary_component_id',
        'override_amount',
        'override_percentage_value',
        'override_percentage_base',
    ];

    protected function casts(): array
    {
        return [
            'override_amount' => 'decimal:2',
            'override_percentage_value' => 'decimal:2',
            'override_percentage_base' => PercentageBase::class,
        ];
    }

    public function employeeSalary(): BelongsTo
    {
        return $this->belongsTo(EmployeeSalary::class);
    }

    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class);
    }
}