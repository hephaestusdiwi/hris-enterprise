<?php

namespace App\Modules\Loan\Models;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Loan\Enums\LoanInstallmentStatus;
use App\Modules\Loan\Enums\LoanInterestType;
use App\Modules\Loan\Enums\LoanStatus;
use App\Modules\Loan\Support\LoanMath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
 
class Loan extends Model
{
    use SoftDeletes;
 
    protected $fillable = [
        'employee_id',
        'principal',
        'interest_rate',
        'interest_type',
        'tenor',
        'installment_amount',
        'total_repayment',
        'first_deduction_period_year',
        'first_deduction_period_month',
        'purpose',
        'status',
        'created_by_user_id',
        'requested_at',
        'decided_at',
        'disbursed_at',
        'completed_at',
        'cancelled_at',
        'cancel_reason',
    ];
 
    protected function casts(): array
    {
        return [
            'principal' => 'decimal:2',
            'interest_rate' => 'decimal:2',
            'interest_type' => LoanInterestType::class,
            'installment_amount' => 'decimal:2',
            'total_repayment' => 'decimal:2',
            'status' => LoanStatus::class,
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
            'disbursed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
 
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
 
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
 
    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class)->orderBy('installment_number');
    }
 
    public function approvalRequest(): HasOne
    {
        return $this->hasOne(LoanApprovalRequest::class);
    }
 
    public function settlement(): HasOne
    {
        return $this->hasOne(LoanSettlement::class);
    }
 
    public function isEditable(): bool
    {
        return $this->status === LoanStatus::Draft;
    }
 
    public function outstandingPrincipal(): string
    {
        $paidPrincipal = $this->installments
            ->where('status', LoanInstallmentStatus::Paid)
            ->reduce(fn (string $carry, LoanInstallment $i) => LoanMath::add($carry, (string) $i->principal_portion), '0.00');
 
        return LoanMath::sub((string) $this->principal, $paidPrincipal);
    }
}