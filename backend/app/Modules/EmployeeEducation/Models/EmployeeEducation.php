<?php

namespace App\Modules\EmployeeEducation\Models;

use App\Modules\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class EmployeeEducation extends Model
{
    protected $table = 'employee_educations';

    public const LEVELS = [
        'sd', 'smp', 'sma_smk', 'd1', 'd2', 'd3', 'd4', 's1', 's2', 's3',
    ];

    public const GRADUATION_STATUSES = ['ongoing', 'graduated', 'dropped_out'];

    protected $fillable = [
        'employee_id',
        'education_level',
        'institution_name',
        'major',
        'start_date',
        'end_date',
        'graduation_status',
        'description',
        'attachment_path',
        'attachment_name',
    ];

    protected $appends = ['attachment_url'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment_path ? Storage::disk('public')->url($this->attachment_path) : null;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}