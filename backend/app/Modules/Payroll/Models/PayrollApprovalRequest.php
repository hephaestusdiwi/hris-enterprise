<?php

namespace App\Modules\Payroll\Models;

use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\Payroll\Enums\PayrollApprovalRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollApprovalRequest extends Model
{
    protected $fillable = ['payroll_run_id', 'approval_flow_id', 'status', 'current_step_sequence', 'requested_at', 'decided_at'];

    protected function casts(): array
    {
        return [
            'status' => PayrollApprovalRequestStatus::class,
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function approvalFlow(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlow::class);
    }

    public function stepDecisions(): HasMany
    {
        return $this->hasMany(PayrollApprovalStepDecision::class)->orderBy('sequence');
    }
}