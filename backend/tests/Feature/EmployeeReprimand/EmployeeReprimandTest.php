<?php

namespace Tests\Feature\EmployeeReprimand;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\EmployeeReprimand\Models\EmployeeReprimand;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeReprimandTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_can_create_reprimand_for_employee(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $hrUser = User::factory()->create();
        $hrUser->assignRole('hr');

        $employee = Employee::factory()->create();

        $response = $this->actingAs($hrUser)->postJson("/api/employees/{$employee->id}/reprimands", [
            'reprimand_type' => 'sp1',
            'title' => 'SP-2026-001',
            'date' => now()->toDateString(),
            'reason' => 'Terlambat masuk kerja lebih dari 5 kali dalam sebulan.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'active');
        // Audit trail: created_by wajib tercatat.
        $this->assertSame($hrUser->id, $response->json('data.created_by_user_id'));
        $this->assertDatabaseHas('employee_reprimands', [
            'employee_id' => $employee->id,
            'title' => 'SP-2026-001',
            'created_by_user_id' => $hrUser->id,
        ]);
    }

    public function test_hr_can_update_active_reprimand(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $hrUser = User::factory()->create();
        $hrUser->assignRole('hr');

        $employee = Employee::factory()->create();
        $reprimand = $employee->reprimands()->create([
            'reprimand_type' => 'sp1',
            'title' => 'SP-2026-001',
            'date' => now()->toDateString(),
            'reason' => 'Alasan awal',
            'status' => 'active',
            'created_by_user_id' => $hrUser->id,
        ]);

        $response = $this->actingAs($hrUser)->postJson("/api/employees/{$employee->id}/reprimands/{$reprimand->id}", [
            'reason' => 'Alasan sudah dikoreksi',
        ]);

        $response->assertOk();
        $this->assertSame('Alasan sudah dikoreksi', $reprimand->fresh()->reason);
    }

    /**
     * Filosofi audit: "hapus" reprimand = void, BUKAN hard delete. Record
     * tetap ada di database (soft-deleted pun tidak), status berubah jadi
     * void dengan reason + siapa + kapan tercatat.
     */
    public function test_hr_can_void_reprimand_instead_of_hard_delete(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $hrUser = User::factory()->create();
        $hrUser->assignRole('hr');

        $employee = Employee::factory()->create();
        $reprimand = $employee->reprimands()->create([
            'reprimand_type' => 'sp1',
            'title' => 'SP-2026-001',
            'date' => now()->toDateString(),
            'reason' => 'Salah input, seharusnya untuk employee lain',
            'status' => 'active',
            'created_by_user_id' => $hrUser->id,
        ]);

        $response = $this->actingAs($hrUser)->postJson("/api/employees/{$employee->id}/reprimands/{$reprimand->id}/void", [
            'reason' => 'Salah input employee',
        ]);

        $response->assertOk();
        $reprimand->refresh();
        $this->assertSame('void', $reprimand->status->value);
        $this->assertSame($hrUser->id, $reprimand->voided_by_user_id);
        $this->assertSame('Salah input employee', $reprimand->void_reason);
        $this->assertNotNull($reprimand->voided_at);
        // Bukan hard delete -- record masih ada di tabel.
        $this->assertDatabaseHas('employee_reprimands', ['id' => $reprimand->id]);
    }

    public function test_voided_reprimand_cannot_be_voided_again_or_edited(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $hrUser = User::factory()->create();
        $hrUser->assignRole('hr');

        $employee = Employee::factory()->create();
        $reprimand = $employee->reprimands()->create([
            'reprimand_type' => 'sp1',
            'title' => 'SP-2026-001',
            'date' => now()->toDateString(),
            'reason' => 'Alasan',
            'status' => 'void',
            'voided_at' => now(),
            'voided_by_user_id' => $hrUser->id,
            'void_reason' => 'Sudah di-void sebelumnya',
        ]);

        $this->actingAs($hrUser)
            ->postJson("/api/employees/{$employee->id}/reprimands/{$reprimand->id}/void", ['reason' => 'Void lagi'])
            ->assertStatus(422);

        $this->actingAs($hrUser)
            ->postJson("/api/employees/{$employee->id}/reprimands/{$reprimand->id}", ['title' => 'Diubah paksa'])
            ->assertStatus(422);
    }

    public function test_employee_can_only_view_own_reprimands_read_only(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $hrUser = User::factory()->create();
        $hrUser->assignRole('hr');

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        $employee->reprimands()->create([
            'reprimand_type' => 'verbal_warning',
            'title' => 'Teguran Lisan',
            'date' => now()->toDateString(),
            'reason' => 'Alasan',
            'status' => 'active',
            'created_by_user_id' => $hrUser->id,
        ]);

        $this->actingAs($employee->user)
            ->getJson('/api/my-reprimands')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /**
     * Guard krusial: employee TIDAK BISA create reprimand untuk dirinya
     * sendiri lewat endpoint admin -- murni wewenang HR/Admin.
     */
    public function test_employee_cannot_create_reprimand_via_admin_endpoint(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        $this->actingAs($employee->user)->postJson("/api/employees/{$employee->id}/reprimands", [
            'reprimand_type' => 'sp1',
            'title' => 'SP Palsu',
            'date' => now()->toDateString(),
            'reason' => 'Coba self-issue',
        ])->assertForbidden();
    }

    public function test_reprimand_belongs_to_employee_relationship(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $hrUser = User::factory()->create();
        $hrUser->assignRole('hr');
        $employee = Employee::factory()->create();

        $reprimand = $employee->reprimands()->create([
            'reprimand_type' => 'sp1',
            'title' => 'SP-2026-001',
            'date' => now()->toDateString(),
            'reason' => 'Alasan',
            'status' => 'active',
            'created_by_user_id' => $hrUser->id,
        ]);

        $this->assertInstanceOf(Employee::class, $reprimand->employee);
        $this->assertSame($employee->id, $reprimand->employee->id);
        $this->assertTrue($employee->reprimands->contains($reprimand));
        $this->assertInstanceOf(EmployeeReprimand::class, $reprimand);
    }
}