<?php

namespace App\Modules\Grooming\Models;

use App\Modules\Branch\Models\Branch;
use App\Modules\Employee\Models\Employee;
use App\Modules\Grooming\Enums\GroomingResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroomingStoreSubmission extends Model
{
    protected $fillable = [
        'branch_id',
        'submitted_by_employee_id',
        'grooming_standard_id',
        'overall_result',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'overall_result' => GroomingResult::class,
            'submitted_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'submitted_by_employee_id');
    }

    public function standard(): BelongsTo
    {
        return $this->belongsTo(GroomingStandard::class, 'grooming_standard_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(GroomingStoreSubmissionAnswer::class);
    }
}