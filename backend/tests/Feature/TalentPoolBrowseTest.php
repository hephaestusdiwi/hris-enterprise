<?php // backend/tests/Feature/TalentPoolBrowseTest.php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Candidate\Models\Candidate;
use App\Modules\Company\Models\Company;
use App\Modules\Department\Models\Department;
use App\Modules\EmploymentType\Models\EmploymentType;
use App\Modules\HiringRequisition\Models\HiringRequisition;
use App\Modules\JobVacancy\Models\JobVacancy;
use App\Modules\Position\Models\Position;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TalentPoolBrowseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function makeVacancy(): JobVacancy
    {
        $company = Company::factory()->create();
        $department = Department::factory()->create(['company_id' => $company->id]);
        $position = Position::factory()->create(['company_id' => $company->id]);
        $employmentType = EmploymentType::factory()->create();
        $requisition = HiringRequisition::factory()->create([
            'company_id' => $company->id, 'department_id' => $department->id,
            'position_id' => $position->id, 'status' => 'open',
        ]);

        return JobVacancy::factory()->create([
            'hiring_requisition_id' => $requisition->id,
            'company_id' => $company->id, 'department_id' => $department->id,
            'position_id' => $position->id, 'employment_type_id' => $employmentType->id,
            'status' => 'published', 'visibility' => 'both', 'application_method' => 'internal',
        ]);
    }

    private function actingAsAuthorizedUser(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view candidates');
        $this->actingAs($user);

        return $user;
    }

    public function test_hold_candidate_appears_in_talent_pool_filter(): void
    {
        $vacancy = $this->makeVacancy();
        $held = Candidate::factory()->create(['job_vacancy_id' => $vacancy->id, 'status' => 'hold', 'full_name' => 'Siti Held']);
        $this->actingAsAuthorizedUser();

        $this->getJson('/api/candidates?status=hold')
            ->assertOk()
            ->assertJsonFragment(['full_name' => 'Siti Held'])
            ->assertJsonPath('data.total', 1);

        $this->assertNotNull($held);
    }

    public function test_non_hold_candidate_does_not_appear_in_talent_pool_filter(): void
    {
        $vacancy = $this->makeVacancy();
        Candidate::factory()->create(['job_vacancy_id' => $vacancy->id, 'status' => 'applied', 'full_name' => 'Budi Applied']);
        $this->actingAsAuthorizedUser();

        $this->getJson('/api/candidates?status=hold')
            ->assertOk()
            ->assertJsonMissing(['full_name' => 'Budi Applied']);
    }

    public function test_search_works_within_talent_pool_filter(): void
    {
        $vacancy = $this->makeVacancy();
        Candidate::factory()->create(['job_vacancy_id' => $vacancy->id, 'status' => 'hold', 'full_name' => 'Ani Wijaya', 'email' => 'ani@example.com']);
        Candidate::factory()->create(['job_vacancy_id' => $vacancy->id, 'status' => 'hold', 'full_name' => 'Rudi Santoso', 'email' => 'rudi@example.com']);
        $this->actingAsAuthorizedUser();

        $this->getJson('/api/candidates?status=hold&search=Ani')
            ->assertOk()
            ->assertJsonFragment(['full_name' => 'Ani Wijaya'])
            ->assertJsonMissing(['full_name' => 'Rudi Santoso']);
    }

    public function test_job_vacancy_filter_works_within_talent_pool(): void
    {
        $vacancyA = $this->makeVacancy();
        $vacancyB = $this->makeVacancy();
        Candidate::factory()->create(['job_vacancy_id' => $vacancyA->id, 'status' => 'hold', 'full_name' => 'Dari Vacancy A']);
        Candidate::factory()->create(['job_vacancy_id' => $vacancyB->id, 'status' => 'hold', 'full_name' => 'Dari Vacancy B']);
        $this->actingAsAuthorizedUser();

        $this->getJson("/api/candidates?status=hold&job_vacancy_id={$vacancyA->id}")
            ->assertOk()
            ->assertJsonFragment(['full_name' => 'Dari Vacancy A'])
            ->assertJsonMissing(['full_name' => 'Dari Vacancy B']);
    }

    public function test_talent_pool_endpoint_requires_view_candidates_permission(): void
    {
        $this->makeVacancy();
        $user = User::factory()->create(); // tanpa permission apapun
        $this->actingAs($user);

        $this->getJson('/api/candidates?status=hold')->assertForbidden();
    }
}