<?php

namespace App\Modules\Employee\Models;

use App\Models\User;
use App\Modules\Branch\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Department\Models\Department;
use App\Modules\EmploymentStatus\Models\EmploymentStatus;
use App\Modules\JobLevel\Models\JobLevel;
use App\Modules\Position\Models\Position;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_number',
        'company_id',
        'branch_id',
        'department_id',
        'position_id',
        'job_level_id',
        'employment_status_id',
        'manager_employee_id',
        'user_id',
        'join_date',
        'resign_date',
        'first_name',
        'last_name',
        'gender',
        'birth_place',
        'birth_date',
        'marital_status',
        'phone',
        'personal_email',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'national_id_number',
        'tax_number',
        'bank_name',
        'bank_account_number',
        'bank_account_holder_name',
    ];

    protected function casts(): array
    {
        return [
            'join_date' => 'date',
            'resign_date' => 'date',
            'birth_date' => 'date',
        ];
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

    public function jobLevel(): BelongsTo
    {
        return $this->belongsTo(JobLevel::class);
    }

    public function employmentStatus(): BelongsTo
    {
        return $this->belongsTo(EmploymentStatus::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_employee_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}