<?php

namespace Tests\Feature\EmployeeEducation;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeEducationTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_create_own_education(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        $response = $this->actingAs($employee->user)->postJson('/api/my-educations', [
            'education_level' => 's1',
            'institution_name' => 'Universitas Indonesia',
            'major' => 'Teknik Informatika',
            'start_date' => '2015-08-01',
            'end_date' => '2019-07-01',
            'graduation_status' => 'graduated',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('employee_educations', [
            'employee_id' => $employee->id,
            'institution_name' => 'Universitas Indonesia',
        ]);
    }

    public function test_employee_can_update_own_education(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        $education = $employee->educations()->create([
            'education_level' => 's1',
            'institution_name' => 'Universitas Lama',
            'graduation_status' => 'graduated',
        ]);

        $response = $this->actingAs($employee->user)->postJson("/api/my-educations/{$education->id}", [
            'institution_name' => 'Universitas Baru',
        ]);

        $response->assertOk();
        $this->assertSame('Universitas Baru', $education->fresh()->institution_name);
    }

    public function test_employee_can_delete_own_education(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        $education = $employee->educations()->create([
            'education_level' => 's1',
            'institution_name' => 'Universitas Indonesia',
            'graduation_status' => 'graduated',
        ]);

        $this->actingAs($employee->user)
            ->deleteJson("/api/my-educations/{$education->id}")
            ->assertOk();

        $this->assertDatabaseMissing('employee_educations', ['id' => $education->id]);
    }

    public function test_education_level_must_be_a_valid_option(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        $response = $this->actingAs($employee->user)->postJson('/api/my-educations', [
            'education_level' => 'not_a_real_level',
            'institution_name' => 'Universitas Indonesia',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('education_level');
    }

    public function test_hr_can_manage_education_for_any_employee(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $hrUser = User::factory()->create();
        $hrUser->assignRole('hr');

        $employee = Employee::factory()->create();

        $this->actingAs($hrUser)->postJson("/api/employees/{$employee->id}/educations", [
            'education_level' => 's1',
            'institution_name' => 'Universitas Indonesia',
        ])->assertCreated();
    }

    /**
     * Guard krusial: employee lain tidak bisa hapus/edit riwayat pendidikan
     * milik employee lain lewat endpoint self-service.
     */
    public function test_employee_cannot_modify_other_employees_education(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $employeeA = Employee::factory()->create();
        $employeeA->user->assignRole('employee');
        $employeeB = Employee::factory()->create();
        $employeeB->user->assignRole('employee');

        $education = $employeeB->educations()->create([
            'education_level' => 's1',
            'institution_name' => 'Universitas Indonesia',
            'graduation_status' => 'graduated',
        ]);

        $this->actingAs($employeeA->user)
            ->deleteJson("/api/my-educations/{$education->id}")
            ->assertForbidden();
    }

    public function test_employee_without_permission_cannot_manage_education_via_admin_endpoint(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        $this->actingAs($employee->user)->postJson("/api/employees/{$employee->id}/educations", [
            'education_level' => 's1',
            'institution_name' => 'Universitas Indonesia',
        ])->assertForbidden();
    }
}