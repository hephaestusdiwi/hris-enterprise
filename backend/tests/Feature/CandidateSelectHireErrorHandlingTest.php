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

class CandidateSelectHireErrorHandlingTest extends TestCase
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
        Permission::firstOrCreate(['name' => 'select candidates', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'hire candidates', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo(['select candidates', 'hire candidates']);
        $this->actingAs($user);

        return $user;
    }

    public function test_select_from_invalid_status_returns_422_not_500(): void
    {
        $candidate = Candidate::factory()->create([
            'job_vacancy_id' => $this->makeVacancy()->id,
            'status' => 'applied', // select() cuma valid dari status 'interview'
        ]);
        $this->actingAsAuthorizedUser();

        $this->postJson("/api/candidates/{$candidate->id}/select")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_hire_from_invalid_status_returns_422_not_500(): void
    {
        $candidate = Candidate::factory()->create([
            'job_vacancy_id' => $this->makeVacancy()->id,
            'status' => 'applied', // hire() cuma valid dari status 'offered' + Offering accepted
        ]);
        $this->actingAsAuthorizedUser();

        $this->postJson("/api/candidates/{$candidate->id}/hire")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}