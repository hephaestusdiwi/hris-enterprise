<?php

namespace App\Modules\Screening\Models;

use App\Modules\Candidate\Models\Candidate;
use App\Modules\Employee\Models\Employee;
use App\Modules\Screening\Enums\ScreeningResult;
use App\Modules\Screening\Enums\ScreeningStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Screening extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_id',
        'reviewer_employee_id',
        'status',
        'result',
        'notes',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ScreeningStatus::class,
            'result' => ScreeningResult::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_employee_id');
    }
}