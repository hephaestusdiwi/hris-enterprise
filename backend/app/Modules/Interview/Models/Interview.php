<?php

namespace App\Modules\Interview\Models;

use App\Models\User;
use App\Modules\Candidate\Models\Candidate;
use App\Modules\Employee\Models\Employee;
use App\Modules\Interview\Enums\InterviewResult;
use App\Modules\Interview\Enums\InterviewStatus;
use App\Modules\JobVacancy\Models\JobVacancy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Interview extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_id',
        'job_vacancy_id',
        'interview_stage_id',
        'interviewer_employee_id',
        'scheduled_by_user_id',
        'scheduled_at',
        'status',
        'result',
        'score',
        'notes',
        'recommendation',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InterviewStatus::class,
            'result' => InterviewResult::class,
            'scheduled_at' => 'datetime',
            'score' => 'integer',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function jobVacancy(): BelongsTo
    {
        return $this->belongsTo(JobVacancy::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(InterviewStage::class, 'interview_stage_id');
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'interviewer_employee_id');
    }

    public function scheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by_user_id');
    }
}