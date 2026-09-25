<?php

namespace App\Modules\Training\Models;

use App\Modules\Employee\Models\Employee;
use App\Modules\Training\Enums\TrainingSessionMode;
use App\Modules\Training\Enums\TrainingSessionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'training_program_id',
        'name',
        'trainer_name',
        'trainer_employee_id',
        'location',
        'mode',
        'start_at',
        'end_at',
        'quota',
        'status',
        'notes',
    ];

    // remaining_days dipakai langsung oleh frontend (badge "H-7", dsb) --
    // sama seperti CompanyObligation::remaining_days.
    protected $appends = ['remaining_days', 'participant_count'];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'mode' => TrainingSessionMode::class,
            'status' => TrainingSessionStatus::class,
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id');
    }

    public function trainerEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'trainer_employee_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TrainingParticipant::class);
    }

    /**
     * Sisa hari sampai start_at (0 = mulai hari ini, negatif = sudah
     * lewat) -- dihitung dari TANGGALnya start_at, bukan jam-nya. Pola
     * & alasan sign-handling sama persis dengan
     * CompanyObligation::getRemainingDaysAttribute() (absolute
     * diffInDays + tanda manual, hindari ambiguitas arah signed
     * diffInDays antar versi Carbon).
     */
    public function getRemainingDaysAttribute(): int
    {
        $today = now()->startOfDay();
        $start = $this->start_at->copy()->startOfDay();
        $days = (int) $today->diffInDays($start);

        return $start->greaterThanOrEqualTo($today) ? $days : -$days;
    }

    public function getParticipantCountAttribute(): int
    {
        return $this->relationLoaded('participants')
            ? $this->participants->count()
            : $this->participants()->count();
    }

    public function isScheduled(): bool
    {
        return $this->status === TrainingSessionStatus::Scheduled;
    }
}