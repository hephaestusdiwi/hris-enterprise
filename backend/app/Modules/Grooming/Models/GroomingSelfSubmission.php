<?php

namespace App\Modules\Grooming\Models;

use App\Modules\Branch\Models\Branch;
use App\Modules\Employee\Models\Employee;
use App\Modules\Grooming\Enums\GroomingResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class GroomingSelfSubmission extends Model
{
    protected $fillable = [
        'employee_id',
        'branch_id',
        'grooming_standard_id',
        'overall_result',
        'photo_path',
        'submitted_at',
    ];

    protected $appends = ['photo_url'];

    protected function casts(): array
    {
        return [
            'overall_result' => GroomingResult::class,
            'submitted_at' => 'datetime',
        ];
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function standard(): BelongsTo
    {
        return $this->belongsTo(GroomingStandard::class, 'grooming_standard_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(GroomingSelfSubmissionAnswer::class);
    }
}