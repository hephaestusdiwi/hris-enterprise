<?php

namespace App\Modules\Payroll\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRunRevision extends Model
{
    protected $fillable = ['payroll_run_id', 'revision_number', 'calculated_at', 'calculated_by_user_id', 'note'];

    protected function casts(): array
    {
        return ['revision_number' => 'integer', 'calculated_at' => 'datetime'];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function calculatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by_user_id');
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }
}