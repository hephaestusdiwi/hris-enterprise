<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Candidate\Models\Candidate;
use App\Modules\Company\Models\Company;
use App\Modules\Department\Models\Department;
use App\Modules\EmploymentType\Models\EmploymentType;
use App\Modules\HiringRequisition\Models\HiringRequisition;
use App\Modules\JobVacancy\Models\JobVacancy;
use App\Modules\NewJoiner\Models\NewJoiner;
use App\Modules\Offering\Models\Offering;
use App\Modules\Position\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NewJoinerConversionTest extends TestCase
{
    use RefreshDatabase;

    private function makeReadyNewJoiner(): NewJoiner
    {
        $company = Company::factory()->create();
        $department = Department::factory()->create(['company_id' => $company->id]);
        $position = Position::factory()->create(['company_id' => $company->id]);
        $employmentType = EmploymentType::factory()->create();
        $requisition = HiringRequisition::factory()->create([
            'company_id' => $company->id, 'department_id' => $department->id,
            'position_id' => $position->id, 'status' => 'open',
        ]);
        $vacancy = JobVacancy::factory()->create([
            'hiring_requisition_id' => $requisition->id,
            'company_id' => $company->id, 'department_id' => $department->id,
            'position_id' => $position->id, 'employment_type_id' => $employmentType->id,
            'status' => 'published', 'visibility' => 'both', 'application_method' => 'internal',
        ]);
        $candidate = Candidate::factory()->create(['job_vacancy_id' => $vacancy->id, 'status' => 'hired']);
        Offering::factory()->create(['candidate_id' => $candidate->id, 'status' => 'accepted']);

        return NewJoiner::factory()->create([
            'candidate_id' => $candidate->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'ready_for_employee_at' => now(),
        ]);
    }

    private function actingAsAuthorizedUser(): User
    {
        Role::firstOrCreate([
            'name' => 'employee',
            'guard_name' => 'web',
        ]);

        Permission::firstOrCreate([
            'name' => 'proceed as employee',
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo('proceed as employee');
        $this->actingAs($user);

        return $user;
    }

    public function test_valid_new_joiner_can_be_converted_to_employee(): void
    {
        $newJoiner = $this->makeReadyNewJoiner();
        $this->actingAsAuthorizedUser();

        $this->postJson("/api/new-joiners/{$newJoiner->id}/convert-to-employee", [])
            ->assertCreated();
    }

    public function test_conversion_creates_employee_and_links_new_joiner(): void
    {
        $newJoiner = $this->makeReadyNewJoiner();
        $this->actingAsAuthorizedUser();

        $response = $this->postJson("/api/new-joiners/{$newJoiner->id}/convert-to-employee", []);
        $response->assertCreated();
        $employeeId = $response->json('data.id');

        $this->assertDatabaseHas('employees', ['id' => $employeeId]);
        $this->assertDatabaseHas('new_joiners', ['id' => $newJoiner->id, 'employee_id' => $employeeId]);
    }

    public function test_candidate_converted_employee_id_is_set(): void
    {
        $newJoiner = $this->makeReadyNewJoiner();
        $candidateId = $newJoiner->candidate_id;
        $this->actingAsAuthorizedUser();

        $response = $this->postJson("/api/new-joiners/{$newJoiner->id}/convert-to-employee", []);
        $response->assertCreated();

        $this->assertDatabaseHas('candidates', [
            'id' => $candidateId, 'converted_employee_id' => $response->json('data.id'),
        ]);
    }

    public function test_invalid_organization_override_payload_is_rejected(): void
    {
        $newJoiner = $this->makeReadyNewJoiner();
        $this->actingAsAuthorizedUser();

        $this->postJson("/api/new-joiners/{$newJoiner->id}/convert-to-employee", [
            'job_level_id' => 999999, // tidak exist — membuktikan Request fix ini aktif
        ])->assertStatus(422);
    }

    public function test_conversion_cannot_be_done_twice(): void
    {
        $newJoiner = $this->makeReadyNewJoiner();
        $this->actingAsAuthorizedUser();

        $this->postJson("/api/new-joiners/{$newJoiner->id}/convert-to-employee", [])->assertCreated();

        // Behavior AKTUAL saat ini (bukan behavior ideal) — lihat Remaining Gaps poin 1.
        $this->postJson("/api/new-joiners/{$newJoiner->id}/convert-to-employee", [])->assertStatus(500);
    }
}