<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Candidate\Models\Candidate;
use App\Modules\Candidate\Models\CandidateStageHistory;
use App\Modules\Company\Models\Company;
use App\Modules\Department\Models\Department;
use App\Modules\EmploymentType\Models\EmploymentType;
use App\Modules\HiringRequisition\Models\HiringRequisition;
use App\Modules\JobVacancy\Models\JobVacancy;
use App\Modules\Position\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CandidateReconsiderDuplicateGuardTest extends TestCase
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
        Permission::firstOrCreate(['name' => 'reconsider candidates', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('reconsider candidates');
        $this->actingAs($user);

        return $user;
    }

    private function makeHoldCandidate(JobVacancy $vacancy): Candidate
    {
        return Candidate::factory()->create(['job_vacancy_id' => $vacancy->id, 'status' => 'hold']);
    }

    public function test_first_reconsideration_to_vacancy_succeeds(): void
    {
        $original = $this->makeHoldCandidate($this->makeVacancy());
        $target = $this->makeVacancy();
        $this->actingAsAuthorizedUser();

        $this->postJson("/api/candidates/{$original->id}/reconsider", ['job_vacancy_id' => $target->id])
            ->assertCreated();
    }

    public function test_second_reconsideration_to_same_vacancy_while_active_is_rejected(): void
    {
        $original = $this->makeHoldCandidate($this->makeVacancy());
        $target = $this->makeVacancy();
        $this->actingAsAuthorizedUser();

        $this->postJson("/api/candidates/{$original->id}/reconsider", ['job_vacancy_id' => $target->id])->assertCreated();

        $this->postJson("/api/candidates/{$original->id}/reconsider", ['job_vacancy_id' => $target->id])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(1, Candidate::where('reconsidered_from_candidate_id', $original->id)->count());
    }

    public function test_same_hold_candidate_can_reconsider_to_different_vacancy(): void
    {
        $original = $this->makeHoldCandidate($this->makeVacancy());
        $vacancyA = $this->makeVacancy();
        $vacancyB = $this->makeVacancy();
        $this->actingAsAuthorizedUser();

        $this->postJson("/api/candidates/{$original->id}/reconsider", ['job_vacancy_id' => $vacancyA->id])->assertCreated();
        $this->postJson("/api/candidates/{$original->id}/reconsider", ['job_vacancy_id' => $vacancyB->id])->assertCreated();
    }

    public function test_reconsider_allowed_again_after_previous_result_is_rejected(): void
    {
        $original = $this->makeHoldCandidate($this->makeVacancy());
        $target = $this->makeVacancy();
        $this->actingAsAuthorizedUser();

        $first = $this->postJson("/api/candidates/{$original->id}/reconsider", ['job_vacancy_id' => $target->id])->assertCreated();
        Candidate::whereKey($first->json('data.id'))->update(['status' => 'rejected']);

        $this->postJson("/api/candidates/{$original->id}/reconsider", ['job_vacancy_id' => $target->id])->assertCreated();

        $this->assertSame(2, Candidate::where('reconsidered_from_candidate_id', $original->id)->count());
    }

    public function test_original_candidate_remains_hold_after_reconsider(): void
    {
        $originalVacancy = $this->makeVacancy();
        $original = $this->makeHoldCandidate($originalVacancy);
        $target = $this->makeVacancy();
        $this->actingAsAuthorizedUser();

        $this->postJson("/api/candidates/{$original->id}/reconsider", ['job_vacancy_id' => $target->id])->assertCreated();

        $this->assertDatabaseHas('candidates', [
            'id' => $original->id, 'status' => 'hold', 'job_vacancy_id' => $originalVacancy->id,
        ]);
    }

    public function test_duplicate_rejection_does_not_create_another_candidate_or_history(): void
    {
        $original = $this->makeHoldCandidate($this->makeVacancy());
        $target = $this->makeVacancy();
        $this->actingAsAuthorizedUser();

        $this->postJson("/api/candidates/{$original->id}/reconsider", ['job_vacancy_id' => $target->id])->assertCreated();

        $candidateCountBefore = Candidate::count();
        $historyCountBefore = CandidateStageHistory::count();

        $this->postJson("/api/candidates/{$original->id}/reconsider", ['job_vacancy_id' => $target->id])->assertStatus(422);

        $this->assertSame($candidateCountBefore, Candidate::count());
        $this->assertSame($historyCountBefore, CandidateStageHistory::count());
    }

    public function test_user_without_permission_gets_403(): void
    {
        $original = $this->makeHoldCandidate($this->makeVacancy());
        $target = $this->makeVacancy();
        $this->actingAs(User::factory()->create());

        $this->postJson("/api/candidates/{$original->id}/reconsider", ['job_vacancy_id' => $target->id])
            ->assertForbidden();
    }

    public function test_reconsideration_chain_remains_valid(): void
    {
        $original = $this->makeHoldCandidate($this->makeVacancy());
        $target = $this->makeVacancy();
        $anotherVacancy = $this->makeVacancy();
        $this->actingAsAuthorizedUser();

        $first = $this->postJson("/api/candidates/{$original->id}/reconsider", ['job_vacancy_id' => $target->id])->assertCreated();
        $firstResultId = $first->json('data.id');
        Candidate::whereKey($firstResultId)->update(['status' => 'hold']);

        $this->postJson("/api/candidates/{$firstResultId}/reconsider", ['job_vacancy_id' => $anotherVacancy->id])->assertCreated();
    }

    public function test_candidate_and_stage_history_are_created_together(): void
    {
        $original = $this->makeHoldCandidate($this->makeVacancy());
        $target = $this->makeVacancy();
        $this->actingAsAuthorizedUser();

        $response = $this->postJson("/api/candidates/{$original->id}/reconsider", ['job_vacancy_id' => $target->id])->assertCreated();
        $newId = $response->json('data.id');

        $this->assertDatabaseHas('candidates', [
            'id' => $newId, 'reconsidered_from_candidate_id' => $original->id,
            'source' => 'talent_pool', 'status' => 'applied',
        ]);
        $this->assertDatabaseHas('candidate_stage_histories', [
            'candidate_id' => $newId, 'from_status' => null, 'to_status' => 'applied',
        ]);
    }
}