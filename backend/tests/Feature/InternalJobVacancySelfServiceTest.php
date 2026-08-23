<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Company\Models\Company;
use App\Modules\Department\Models\Department;
use App\Modules\Employee\Models\Employee;
use App\Modules\EmploymentType\Models\EmploymentType;
use App\Modules\HiringRequisition\Models\HiringRequisition;
use App\Modules\JobVacancy\Models\JobVacancy;
use App\Modules\Position\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class InternalJobVacancySelfServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeVacancy(
        string $visibility,
        array $overrides = [],
    ): JobVacancy {
        $company = Company::factory()->create();

        $department = Department::factory()->create([
            'company_id' => $company->id,
        ]);

        $position = Position::factory()->create([
            'company_id' => $company->id,
        ]);

        $employmentType = EmploymentType::factory()->create();

        $requisition = HiringRequisition::factory()->create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'status' => 'open',
        ]);

        return JobVacancy::factory()->create(array_merge([
            'hiring_requisition_id' => $requisition->id,
            'company_id' => $company->id,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'employment_type_id' => $employmentType->id,
            'status' => 'published',
            'visibility' => $visibility,
            'application_method' => 'internal',
        ], $overrides));
    }

    private function actingAsEmployee(): Employee
    {
        $user = User::factory()->create();

        $employee = Employee::factory()->create([
            'user_id' => $user->id,
            'personal_email' => 'employee.internal@example.com',
        ]);

        $this->actingAs($user);

        return $employee;
    }

    public function test_employee_can_see_internal_vacancy(): void
    {
        $vacancy = $this->makeVacancy('internal');

        $this->actingAsEmployee();

        $this->getJson('/api/job-vacancies/self-service')
            ->assertOk()
            ->assertJsonFragment([
                'slug' => $vacancy->slug,
            ]);
    }

    public function test_employee_cannot_see_external_only_vacancy(): void
    {
        $vacancy = $this->makeVacancy('external');

        $this->actingAsEmployee();

        $this->getJson('/api/job-vacancies/self-service')
            ->assertOk()
            ->assertJsonMissing([
                'slug' => $vacancy->slug,
            ]);
    }

    public function test_employee_can_open_internal_vacancy_detail(): void
    {
        $vacancy = $this->makeVacancy('both');

        $this->actingAsEmployee();

        $this->getJson(
            "/api/job-vacancies/self-service/{$vacancy->slug}"
        )->assertOk();
    }

    public function test_self_service_endpoint_requires_authentication(): void
    {
        $this->makeVacancy('internal');

        $this->getJson('/api/job-vacancies/self-service')
            ->assertUnauthorized();
    }

    public function test_employee_can_apply_and_identity_is_derived_from_own_profile(): void
    {
        $vacancy = $this->makeVacancy('internal');

        $employee = $this->actingAsEmployee();

        $this->postJson(
            "/api/job-vacancies/self-service/{$vacancy->slug}/apply",
            [
                'cv' => UploadedFile::fake()->create(
                    'cv.pdf',
                    500,
                    'application/pdf',
                ),
            ],
        )->assertCreated();

        $this->assertDatabaseHas('candidates', [
            'job_vacancy_id' => $vacancy->id,
            'email' => $employee->personal_email,
            'source' => 'internal',
            'status' => 'applied',
        ]);
    }
}