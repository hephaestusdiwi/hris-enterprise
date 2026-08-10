<?php

namespace App\Modules\Payroll\Models;

use App\Modules\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payslip extends Model
{
    protected $fillable = [
        'payroll_run_id', 'payroll_run_revision_id', 'employee_id',
        'gross_earning', 'structural_deduction', 'manual_deduction_total',
        'bpjs_employee_total', 'bpjs_employer_total', 'tax_amount', 'loan_deduction_total', 'net_pay',
        'is_published', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'gross_earning' => 'decimal:2',
            'structural_deduction' => 'decimal:2',
            'manual_deduction_total' => 'decimal:2',
            'bpjs_employee_total' => 'decimal:2',
            'bpjs_employer_total' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'loan_deduction_total' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'is_published' => 'boolean',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function payrollRunRevision(): BelongsTo
    {
        return $this->belongsTo(PayrollRunRevision::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayslipLine::class);
    }
}