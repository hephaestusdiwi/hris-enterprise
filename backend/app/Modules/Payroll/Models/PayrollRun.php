<?php

namespace App\Modules\Payroll\Models;

use App\Models\User;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollRun extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'period_year', 'period_month', 'cutoff_date', 'payment_date',
        'status', 'current_revision', 'created_by_user_id',
        'requested_at', 'decided_at', 'processed_at',
        'locked_at', 'locked_by_user_id', 'published_at', 'published_by_user_id',
        'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => PayrollRunStatus::class,
            'cutoff_date' => 'date',
            'payment_date' => 'date',
            'current_revision' => 'integer',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
            'processed_at' => 'datetime',
            'locked_at' => 'datetime',
            'published_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'payroll_run_participants');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PayrollRunRevision::class)->orderBy('revision_number');
    }

    public function currentRevision(): HasOne
    {
        return $this->hasOne(PayrollRunRevision::class)->ofMany('revision_number', 'max');
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function approvalRequest(): HasOne
    {
        return $this->hasOne(PayrollApprovalRequest::class)->latestOfMany('id');
    }

    public function isEditableParticipants(): bool
    {
        return $this->status === PayrollRunStatus::Draft;
    }
}