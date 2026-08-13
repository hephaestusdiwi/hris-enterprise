<?php

namespace App\Modules\Loan\Models;
 
use App\Models\User;
use App\Modules\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanSettlement extends Model
{
    protected $fillable = [
        'loan_id',
        'employee_id',
        'resign_date',
        'final_payroll_period_year',
        'final_payroll_period_month',
        'outstanding_principal_settled',
        'superseded_installment_count',
        'initiated_by_user_id',
        'note',
    ];
 
    protected function casts(): array
    {
        return [
            'resign_date' => 'date',
            'outstanding_principal_settled' => 'decimal:2',
        ];
    }
 
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
 
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
 
    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }
 
    /**
     * Installment yang tersentuh oleh settlement ini: baik yang di-supersede
     * (skipped) maupun baris lump-sum baru hasil settlement itu sendiri.
     */
    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class);
    }
}