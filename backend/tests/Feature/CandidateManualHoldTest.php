<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Candidate\Models\Candidate;
use App\Modules\Company\Models\Company;
use App\Modules\Department\Models\Department;
use App\Modules\EmploymentType\Models\EmploymentType;
use App\Modules\HiringRequisition\Models\HiringRequisition;
use App\Modules\JobVacancy\Models\JobVacancy;
use App\Modules\Position\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CandidateManualHoldTest extends TestCase
{
    use RefreshDatabase;

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
        // Permission yang dibutuhkan oleh keseluruhan flow test:
        // Hold → Talent Pool browse → Reconsider.
        foreach ([
            'hold candidates',
            'view candidates',
            'reconsider candidates',
        ] as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $user = User::factory()->create();
        $user->givePermissionTo([
            'hold candidates',
            'view candidates',
            'reconsider candidates',
        ]);

        $this->actingAs($user);

        return $user;
    }

    private function makeCandidate(JobVacancy $vacancy, string $status): Candidate
    {
        return Candidate::factory()->create([
            'job_vacancy_id' => $vacancy->id,
            'status' => $status,
            'source' => 'career_site',
        ]);
    }

    /** @dataProvider allowedStatusProvider */
    public function test_candidate_in_allowed_status_can_be_manually_held(string $status): void
    {
        $vacancy = $this->makeVacancy();
        $candidate = $this->makeCandidate($vacancy, $status);
        $this->actingAsAuthorizedUser();

        $this->postJson("/api/candidates/{$candidate->id}/hold", ['notes' => 'Kandidat bagus, simpan dulu'])
            ->assertOk()
            ->assertJsonPath('data.status', 'hold');

        $this->assertDatabaseHas('candidates', [
            'id' => $candidate->id,
            'status' => 'hold',
            'job_vacancy_id' => $vacancy->id,
            'source' => 'career_site',
        ]);
    }

    public static function allowedStatusProvider(): array
    {
        return [['applied'], ['screening'], ['interview'], ['selected']];
    }

    /** @dataProvider blockedStatusProvider */
    public function test_candidate_in_blocked_status_is_rejected_with_422(string $status): void
    {
        $vacancy = $this->makeVacancy();
        $candidate = $this->makeCandidate($vacancy, $status);
        $this->actingAsAuthorizedUser();

        $this->postJson("/api/candidates/{$candidate->id}/hold")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('candidates', ['id' => $candidate->id, 'status' => $status]);
    }

    public static function blockedStatusProvider(): array
    {
        return [['offering'], ['offered'], ['hired'], ['rejected'], ['hold']];
    }

    public function test_stage_history_is_created_with_correct_transition_and_notes(): void
    {
        $vacancy = $this->makeVacancy();
        $candidate = $this->makeCandidate($vacancy, 'applied');
        $this->actingAsAuthorizedUser();

        $this->postJson("/api/candidates/{$candidate->id}/hold", ['notes' => 'Alasan tertentu'])->assertOk();

        $this->assertDatabaseHas('candidate_stage_histories', [
            'candidate_id' => $candidate->id,
            'from_status' => 'applied',
            'to_status' => 'hold',
            'notes' => 'Alasan tertentu',
        ]);
    }

    public function test_user_without_permission_gets_403(): void
    {
        $vacancy = $this->makeVacancy();
        $candidate = $this->makeCandidate($vacancy, 'applied');
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson("/api/candidates/{$candidate->id}/hold")->assertForbidden();
    }

    public function test_manually_held_candidate_appears_in_talent_pool_filter(): void
    {
        $vacancy = $this->makeVacancy();
        $candidate = $this->makeCandidate($vacancy, 'selected');
        $this->actingAsAuthorizedUser();

        $this->postJson("/api/candidates/{$candidate->id}/hold")->assertOk();

        $this->getJson('/api/candidates?status=hold')
            ->assertOk()
            ->assertJsonFragment(['id' => $candidate->id]);
    }

    public function test_manually_held_candidate_can_be_reconsidered(): void
    {
        $originalVacancy = $this->makeVacancy();
        $targetVacancy = $this->makeVacancy();
        $candidate = $this->makeCandidate($originalVacancy, 'interview');
        $this->actingAsAuthorizedUser();

        $this->postJson("/api/candidates/{$candidate->id}/hold")->assertOk();

        $this->postJson("/api/candidates/{$candidate->id}/reconsider", [
            'job_vacancy_id' => $targetVacancy->id,
        ])->assertCreated();

        $this->assertDatabaseHas('candidates', [
            'reconsidered_from_candidate_id' => $candidate->id,
            'job_vacancy_id' => $targetVacancy->id,
            'source' => 'talent_pool',
            'status' => 'applied',
        ]);
    }
}