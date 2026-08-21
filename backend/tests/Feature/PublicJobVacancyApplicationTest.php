<?php

namespace Tests\Feature;

use App\Modules\Candidate\Models\Candidate;
use App\Modules\Company\Models\Company;
use App\Modules\Department\Models\Department;
use App\Modules\EmploymentType\Models\EmploymentType;
use App\Modules\HiringRequisition\Models\HiringRequisition;
use App\Modules\JobVacancy\Models\JobVacancy;
use App\Modules\Position\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PublicJobVacancyApplicationTest extends TestCase
{
    use RefreshDatabase;

    private function makePublishedVacancy(array $overrides = []): JobVacancy
    {
        $company = Company::factory()->create();
        $department = Department::factory()->create(['company_id' => $company->id]);
        $position = Position::factory()->create(['company_id' => $company->id]);
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
            'status' => 'published',
            'visibility' => 'external',
            'application_method' => 'internal',
        ], $overrides));
    }

    public function test_published_vacancy_appears_in_public_listing(): void
    {
        $vacancy = $this->makePublishedVacancy();

        $this->getJson('/api/careers/vacancies')
            ->assertOk()
            ->assertJsonFragment(['slug' => $vacancy->slug]);
    }

    public function test_draft_vacancy_does_not_appear_in_public_listing(): void
    {
        $vacancy = $this->makePublishedVacancy(['status' => 'draft']);

        $this->getJson('/api/careers/vacancies')
            ->assertOk()
            ->assertJsonMissing(['slug' => $vacancy->slug]);
    }

    public function test_closed_cancelled_archived_vacancies_do_not_appear(): void
    {
        foreach (['closed', 'cancelled', 'archived'] as $status) {
            $vacancy = $this->makePublishedVacancy(['status' => $status]);
            $this->getJson('/api/careers/vacancies')->assertJsonMissing(['slug' => $vacancy->slug]);
        }
    }

    public function test_public_detail_only_opens_published_vacancy(): void
    {
        $vacancy = $this->makePublishedVacancy(['status' => 'draft']);

        $this->getJson("/api/careers/vacancies/{$vacancy->slug}")->assertNotFound();
    }

    public function test_candidate_can_apply_and_is_created_with_applied_status(): void
    {
        $vacancy = $this->makePublishedVacancy();

        $response = $this->postJson("/api/careers/vacancies/{$vacancy->slug}/apply", [
            'full_name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'phone' => '081234567890',
            'source' => 'career_site',
            'cv' => UploadedFile::fake()->create('cv.pdf', 500),
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('candidates', [
            'email' => 'budi@example.com',
            'job_vacancy_id' => $vacancy->id,
            'status' => 'applied',
        ]);
    }

    public function test_duplicate_application_to_same_vacancy_is_rejected(): void
    {
        $vacancy = $this->makePublishedVacancy();

        Candidate::factory()->create([
            'job_vacancy_id' => $vacancy->id,
            'email' => 'budi@example.com',
            'status' => 'applied',
        ]);

        $this->postJson("/api/careers/vacancies/{$vacancy->slug}/apply", [
            'full_name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'phone' => '081234567890',
            'source' => 'career_site',
            'cv' => UploadedFile::fake()->create('cv.pdf', 500),
        ])->assertStatus(422);
    }

    public function test_same_email_can_apply_to_different_vacancy(): void
    {
        $vacancyA = $this->makePublishedVacancy();
        $vacancyB = $this->makePublishedVacancy();

        Candidate::factory()->create([
            'job_vacancy_id' => $vacancyA->id,
            'email' => 'budi@example.com',
            'status' => 'applied',
        ]);

        $this->postJson("/api/careers/vacancies/{$vacancyB->slug}/apply", [
            'full_name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'phone' => '081234567890',
            'source' => 'career_site',
            'cv' => UploadedFile::fake()->create('cv.pdf', 500),
        ])->assertCreated();
    }

    public function test_public_endpoints_do_not_require_authentication(): void
    {
        $vacancy = $this->makePublishedVacancy();

        // Tidak ada actingAs() sama sekali di test ini — itulah yang dites
        $this->getJson('/api/careers/vacancies')->assertOk();
        $this->getJson("/api/careers/vacancies/{$vacancy->slug}")->assertOk();
    }
}