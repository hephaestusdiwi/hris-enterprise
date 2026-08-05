<?php

namespace App\Modules\JobVacancy\Models;

use App\Modules\Branch\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Department\Models\Department;
use App\Modules\Employee\Models\Employee;
use App\Modules\EmploymentType\Models\EmploymentType;
use App\Modules\HiringRequisition\Models\HiringRequisition;
use App\Modules\JobVacancy\Enums\JobVacancyStatus;
use App\Modules\JobVacancy\Enums\VacancyVisibility;
use App\Modules\JobVacancy\Enums\ApplicationMethod;
use App\Modules\Position\Models\Position;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobVacancy extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'hiring_requisition_id',
        'company_id',
        'branch_id',
        'department_id',
        'position_id',
        'hiring_manager_employee_id',
        'recruiter_employee_id',
        'title',
        'slug',
        'description',
        'requirements',
        'employment_type_id',
        'visibility',
        'status',
        'application_deadline',
        'published_at',
        'paused_at',
        'closed_at',
        'filled_at',
        'cancelled_at',
        'archived_at',
        'application_method',
        'external_apply_url',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => VacancyVisibility::class,
            'status' => JobVacancyStatus::class,
            'application_method' => ApplicationMethod::class,
            'application_deadline' => 'date',
            'published_at' => 'datetime',
            'paused_at' => 'datetime',
            'closed_at' => 'datetime',
            'filled_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function hiringRequisition(): BelongsTo
    {
        return $this->belongsTo(HiringRequisition::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function hiringManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'hiring_manager_employee_id');
    }

    public function recruiter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'recruiter_employee_id');
    }

    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class);
    }

    // STEP berikutnya tinggal nambah relasi baru di sini, tanpa migration/refactor:
    // public function candidates(): HasMany { return $this->hasMany(Candidate::class); }
    // public function pipelineStages(): HasMany { return $this->hasMany(PipelineStage::class); }
}