<?php

namespace Database\Factories\Modules\JobVacancy\Models;

use App\Modules\Company\Models\Company;
use App\Modules\Department\Models\Department;
use App\Modules\Employee\Models\Employee;
use App\Modules\EmploymentType\Models\EmploymentType;
use App\Modules\HiringRequisition\Models\HiringRequisition;
use App\Modules\JobVacancy\Enums\JobVacancyStatus;
use App\Modules\JobVacancy\Models\JobVacancy;
use App\Modules\Position\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JobVacancy>
 */
class JobVacancyFactory extends Factory
{
    protected $model = JobVacancy::class;

    public function definition(): array
    {
        return [
            'hiring_requisition_id' => HiringRequisition::factory(),
            'company_id' => Company::factory(),
            'branch_id' => null,
            'department_id' => Department::factory(),
            'position_id' => Position::factory(),
            'hiring_manager_employee_id' => Employee::factory(),
            'recruiter_employee_id' => Employee::factory(),
            'title' => fake()->jobTitle(),
            'slug' => fn (array $attributes) => Str::slug($attributes['title']).'-'.Str::lower(Str::random(8)),
            'description' => fake()->paragraphs(3, true),
            'requirements' => fake()->paragraphs(2, true),
            'employment_type_id' => EmploymentType::factory(),
            'visibility' => 'public',
            'application_method' => 'internal',
            'external_apply_url' => null,
            'status' => JobVacancyStatus::Draft->value,
            'application_deadline' => now()->addMonth()->toDateString(),
            'published_at' => null,
            'paused_at' => null,
            'closed_at' => null,
            'cancelled_at' => null,
            'filled_at' => null,
            'archived_at' => null,
        ];
    }
}