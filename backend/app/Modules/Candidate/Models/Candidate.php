<?php
namespace App\Modules\Candidate\Models;

use App\Modules\Candidate\Enums\CandidateSource;
use App\Modules\Candidate\Enums\CandidateStatus;
use App\Modules\Employee\Models\Employee;
use App\Modules\JobVacancy\Models\JobVacancy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Candidate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'job_vacancy_id',
        'full_name',
        'email',
        'phone',
        'source',
        'cv_path',
        'status',
        'score',
        'notes',
        'converted_employee_id',
        'applied_at',
        'held_at',
        'hired_at',
        'rejected_at',
        'reconsidered_from_candidate_id',
    ];

    protected function casts(): array
    {
        return [
            'source' => CandidateSource::class,
            'status' => CandidateStatus::class,
            'score' => 'integer',
            'applied_at' => 'datetime',
            'held_at' => 'datetime',
            'hired_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function jobVacancy(): BelongsTo
    {
        return $this->belongsTo(JobVacancy::class);
    }

    public function convertedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'converted_employee_id');
    }

    public function stageHistories(): HasMany
    {
        return $this->hasMany(CandidateStageHistory::class)->orderBy('changed_at');
    }

    public function reconsideredFrom(): BelongsTo
    {
        return $this->belongsTo(Candidate::class, 'reconsidered_from_candidate_id');
    }

    public function reconsiderations(): HasMany
    {
        return $this->hasMany(Candidate::class, 'reconsidered_from_candidate_id');
    }

    // Phase 4+ tinggal nambah relasi di sini tanpa ubah tabel ini:
    // public function screenings(): HasMany { return $this->hasMany(Screening::class); }
    // public function interviews(): HasMany { return $this->hasMany(Interview::class); }
}