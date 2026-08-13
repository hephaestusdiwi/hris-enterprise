<?php

namespace App\Modules\Loan\Models;

use App\Modules\EmployeeDeduction\Models\EmployeeDeduction;
use App\Modules\Loan\Enums\LoanInstallmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanInstallment extends Model
{
    protected $fillable = [
        'loan_id',
        'installment_number',
        'payroll_period_year',
        'payroll_period_month',
        'principal_portion',
        'interest_portion',
        'original_amount',
        'amount',
        'status',
        'paid_at',
        'employee_deduction_id',
        'loan_settlement_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'principal_portion' => 'decimal:2',
            'interest_portion' => 'decimal:2',
            'original_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'status' => LoanInstallmentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function employeeDeduction(): BelongsTo
    {
        return $this->belongsTo(EmployeeDeduction::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(LoanSettlement::class, 'loan_settlement_id');
    }
}