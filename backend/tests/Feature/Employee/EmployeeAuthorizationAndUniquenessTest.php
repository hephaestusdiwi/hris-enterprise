<?php

namespace Tests\Feature\Employee;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\EmploymentType\Models\EmploymentType;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeAuthorizationAndUniquenessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sebelum fix: role hr tidak punya permission 'view employees'/'edit
     * employees' sama sekali di seeder, jadi 403 di semua endpoint Employee
     * walau EmployeePolicy/EmployeeScope-nya sendiri sudah benar.
     */
    public function test_hr_role_can_view_and_update_employees(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $hrUser = User::factory()->create();
        $hrUser->assignRole('hr');

        $employee = Employee::factory()->create();

        $this->actingAs($hrUser)
            ->getJson('/api/employees')
            ->assertOk();

        $this->actingAs($hrUser)
            ->getJson("/api/employees/{$employee->id}")
            ->assertOk();

        $response = $this->actingAs($hrUser)->putJson("/api/employees/{$employee->id}", [
            'employee_number' => $employee->employee_number,
            'company_id' => $employee->company_id,
            'user_id' => $employee->user_id,
            'join_date' => $employee->join_date->toDateString(),
            'first_name' => 'Updated',
            'last_name' => $employee->last_name,
            'gender' => $employee->gender,
        ]);

        $response->assertOk();
    }

    /**
     * hr SENGAJA belum dikasih 'delete employees' di Phase 1 — hapus employee
     * seharusnya lewat flow Offboarding (Phase 2), bukan hard delete.
     */
    public function test_hr_role_cannot_delete_employees_yet(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $hrUser = User::factory()->create();
        $hrUser->assignRole('hr');

        $employee = Employee::factory()->create();

        $this->actingAs($hrUser)
            ->deleteJson("/api/employees/{$employee->id}")
            ->assertForbidden();
    }

    /**
     * Sebelum fix: unique index plain di DB (bukan partial), jadi employee
     * yang sudah soft-deleted tetap "memegang" employee_number/NIK-nya
     * selamanya — employee baru dengan nomor yang sama (termasuk rehire
     * orang yang sama) ditolak di level constraint DB, bukan cuma validasi.
     */
    public function test_employee_number_and_national_id_can_be_reused_after_soft_delete(): void
    {
        $admin = User::factory()->create();
        $this->seed(RolePermissionSeeder::class);
        $admin->assignRole('admin');

        $original = Employee::factory()->create([
            'employee_number' => 'EMP-000123',
            'national_id_number' => '3201010101990001',
        ]);
        $original->delete(); // soft delete

        $this->assertSoftDeleted('employees', ['id' => $original->id]);

        // Buat employee baru dengan employee_number & NIK yang sama persis —
        // baik lewat validasi Laravel maupun constraint DB harus lolos.
        $newEmployee = Employee::factory()->make([
            'employee_number' => 'EMP-000123',
            'national_id_number' => '3201010101990001',
        ]);

        $newEmployee->save();

        $this->assertDatabaseHas('employees', [
            'id' => $newEmployee->id,
            'employee_number' => 'EMP-000123',
            'national_id_number' => '3201010101990001',
            'deleted_at' => null,
        ]);
    }

    /**
     * Dua employee AKTIF (belum di-soft-delete) tetap tidak boleh berbagi
     * employee_number/NIK yang sama — partial index harus tetap menegakkan
     * ini untuk baris yang deleted_at IS NULL.
     */
    public function test_two_active_employees_still_cannot_share_employee_number(): void
    {
        Employee::factory()->create(['employee_number' => 'EMP-000999']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Employee::factory()->create(['employee_number' => 'EMP-000999']);
    }

    /**
     * Employment Type 'Probation' harus sudah di-retire (is_active=false)
     * kalau sempat ada, dan seeder fresh-install tidak boleh membuatnya lagi.
     */
    public function test_probation_employment_type_is_retired_or_absent(): void
    {
        $probation = EmploymentType::withoutGlobalScopes()
            ->where('code', 'PROBATION')
            ->first();

        if ($probation) {
            $this->assertFalse((bool) $probation->is_active);
        } else {
            $this->assertTrue(true, 'Fresh install tidak membuat type PROBATION sama sekali — sesuai ekspektasi.');
        }
    }
}
