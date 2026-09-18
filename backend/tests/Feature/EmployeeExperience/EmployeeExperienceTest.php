<?php

namespace Tests\Feature\EmployeeExperience;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_create_own_experience(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        $response = $this->actingAs($employee->user)->postJson('/api/my-experiences', [
            'company_name' => 'PT Contoh Sejahtera',
            'position_title' => 'Backend Developer',
            'employment_type' => 'full_time',
            'start_date' => '2020-01-01',
            'end_date' => '2023-01-01',
            'reason_for_leaving' => 'Mencari tantangan baru',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('employee_experiences', [
            'employee_id' => $employee->id,
            'company_name' => 'PT Contoh Sejahtera',
        ]);
    }

    public function test_employee_can_update_own_experience(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        $experience = $employee->experiences()->create([
            'company_name' => 'PT Lama',
            'position_title' => 'Staff',
            'start_date' => '2018-01-01',
        ]);

        $this->actingAs($employee->user)
            ->putJson("/api/my-experiences/{$experience->id}", ['company_name' => 'PT Baru'])
            ->assertOk();

        $this->assertSame('PT Baru', $experience->fresh()->company_name);
    }

    public function test_employee_can_delete_own_experience(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        $experience = $employee->experiences()->create([
            'company_name' => 'PT Contoh',
            'position_title' => 'Staff',
            'start_date' => '2018-01-01',
        ]);

        $this->actingAs($employee->user)
            ->deleteJson("/api/my-experiences/{$experience->id}")
            ->assertOk();

        $this->assertDatabaseMissing('employee_experiences', ['id' => $experience->id]);
    }

    public function test_end_date_cannot_be_before_start_date(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        $response = $this->actingAs($employee->user)->postJson('/api/my-experiences', [
            'company_name' => 'PT Contoh',
            'position_title' => 'Staff',
            'start_date' => '2020-01-01',
            'end_date' => '2019-01-01',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('end_date');
    }

    public function test_hr_can_manage_experience_for_any_employee(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $hrUser = User::factory()->create();
        $hrUser->assignRole('hr');

        $employee = Employee::factory()->create();

        $this->actingAs($hrUser)->postJson("/api/employees/{$employee->id}/experiences", [
            'company_name' => 'PT Contoh',
            'position_title' => 'Staff',
            'start_date' => '2020-01-01',
        ])->assertCreated();
    }

    /**
     * Guard krusial: employee lain tidak bisa ubah pengalaman kerja milik
     * employee lain lewat endpoint self-service.
     */
    public function test_employee_cannot_modify_other_employees_experience(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $employeeA = Employee::factory()->create();
        $employeeA->user->assignRole('employee');
        $employeeB = Employee::factory()->create();
        $employeeB->user->assignRole('employee');

        $experience = $employeeB->experiences()->create([
            'company_name' => 'PT Contoh',
            'position_title' => 'Staff',
            'start_date' => '2018-01-01',
        ]);

        $this->actingAs($employeeA->user)
            ->putJson("/api/my-experiences/{$experience->id}", ['company_name' => 'Diubah Paksa'])
            ->assertForbidden();
    }
}