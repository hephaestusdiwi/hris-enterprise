<?php

namespace App\Modules\LeaveBalance\Models;

use App\Modules\Employee\Models\Employee;
use App\Modules\LeaveBalance\Support\LeaveBalanceMath;
use App\Modules\LeaveType\Models\LeaveType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveBalance extends Model
{
    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'period_start',
        'period_end',
        'eligible_from',
        'initial_quota',
        'carry_over_days',
        'carry_over_expiry_date',
        'used_days',
        'generated_at',
    ];

    protected $appends = ['remaining_days'];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'eligible_from' => 'date',
            'initial_quota' => 'decimal:2',
            'carry_over_days' => 'decimal:2',
            'carry_over_expiry_date' => 'date',
            'used_days' => 'decimal:2',
            'generated_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(LeaveBalanceAdjustment::class);
    }

    /**
     * Versi presisi (string) — dipakai internal engine (mis. hitung carry over ke periode
     * berikutnya) agar tidak ada round-trip lewat float PHP native sama sekali.
     */
    public function remainingDaysAsString(): ?string
    {
        if ($this->initial_quota === null) {
            return null;
        }

        $adjustments = $this->relationLoaded('adjustments') ? $this->adjustments : $this->adjustments()->get();

        $adjustmentTotal = $adjustments->reduce(
            fn (string $carry, LeaveBalanceAdjustment $adjustment) => LeaveBalanceMath::add($carry, (string) $adjustment->adjustment_days),
            '0'
        );

        $result = LeaveBalanceMath::add((string) $this->initial_quota, (string) $this->carry_over_days);
        $result = LeaveBalanceMath::add($result, $adjustmentTotal);

        return LeaveBalanceMath::sub($result, (string) $this->used_days);
    }

    /**
     * Versi float — buat response API/JSON doang. Jangan dipakai buat perhitungan lanjutan
     * di dalam engine; pakai remainingDaysAsString() untuk itu.
     */
    public function getRemainingDaysAttribute(): ?float
    {
        $raw = $this->remainingDaysAsString();

        return $raw !== null ? (float) $raw : null;
    }
}