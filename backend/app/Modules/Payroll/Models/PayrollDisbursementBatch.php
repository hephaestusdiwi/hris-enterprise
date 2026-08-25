<?php

namespace App\Modules\Payroll\Models;

use App\Modules\Payroll\Enums\PayrollDisbursementStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollDisbursementBatch extends Model
{
    protected $fillable = [
        'payroll_run_id', 'payroll_run_revision_id', 'status',
        'total_amount', 'total_employee_count',
        'generated_by_user_id', 'generated_at',
        'sent_by_user_id', 'sent_at',
        'decided_by_user_id', 'decided_at', 'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => PayrollDisbursementStatus::class,
            'total_amount' => 'decimal:2',
            'generated_at' => 'datetime',
            'sent_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(PayrollRunRevision::class, 'payroll_run_revision_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollDisbursementItem::class);
    }
}
