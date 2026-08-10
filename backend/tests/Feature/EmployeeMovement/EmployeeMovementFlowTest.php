<?php

namespace Tests\Feature\EmployeeMovement;

use App\Models\User;
use App\Modules\ApprovalFlow\Enums\ApproverType;
use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\ApprovalFlow\Models\ApprovalStep;
use App\Modules\Employee\Models\Employee;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeMovementFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sesuai preseden HiringRequisition: tanpa ApprovalFlow yang cocok,
     * movement TIDAK dibuat sama sekali (bukan auto-approve).
     */
    public function test_movement_creation_blocked_without_matching_approval_flow(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $employee = Employee::factory()->create();

        $response = $this->actingAs($admin)->postJson("/api/employees/{$employee->id}/movements", [
            'movement_type' => 'transfer',
            'effective_date' => now()->toDateString(),
            'company_id' => $employee->company_id,
            'branch_id' => null,
            'department_id' => null,
            'position_id' => null,
            'manager_employee_id' => null,
        ]);

        $response->assertStatus(500); // EmployeeMovementException, belum ada exception-handler khusus di test env

        $this->assertDatabaseCount('employee_movements', 0);
    }

    /**
     * Movement dengan effective_date HARI INI, begitu full-approved, langsung
     * ter-apply ke current state Employee.
     */
    public function test_movement_applies_immediately_when_effective_date_is_today(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $employee = Employee::factory()->create();

        $approvalFlow = ApprovalFlow::create([
            'company_id' => $employee->company_id,
            'name' => 'Default Flow',
            'code' => 'DEFAULT-'.$employee->company_id,
            'is_active' => true,
        ]);

        $step = ApprovalStep::create([
            'approval_flow_id' => $approvalFlow->id,
            'sequence' => 1,
            'approver_type' => ApproverType::SpecificEmployee->value,
            'approver_employee_id' => $employee->id, // sengaja approver = dirinya sendiri, cuma buat simplifikasi test
            'is_active' => true,
        ]);

        $movementResponse = $this->actingAs($admin)->postJson("/api/employees/{$employee->id}/movements", [
            'movement_type' => 'transfer',
            'effective_date' => now()->toDateString(),
            'company_id' => $employee->company_id,
            'branch_id' => null,
            'department_id' => null,
            'position_id' => null,
            'manager_employee_id' => null,
        ]);

        $movementResponse->assertStatus(201);
        $movementId = $movementResponse->json('data.id');

        $decisionId = \App\Modules\EmployeeMovement\Models\EmployeeMovementApprovalStepDecision::first()->id;

        $decideResponse = $this->actingAs($employee->user)->postJson(
            "/api/employee-movements/approvals/{$decisionId}/decide",
            ['action' => 'approve']
        );

        $decideResponse->assertOk();

        $movement = \App\Modules\EmployeeMovement\Models\EmployeeMovement::find($movementId);
        $this->assertSame('applied', $movement->status->value);
        $this->assertNotNull($movement->applied_at);
    }

    /**
     * UpdateEmployeeRequest menolak percobaan ubah field lifecycle-controlled,
     * tapi TETAP menerima no-op (nilai sama seperti current state) —
     * backward compatible untuk frontend yang masih resend payload lengkap.
     */
    public function test_update_employee_rejects_lifecycle_field_change_but_allows_noop(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $employee = Employee::factory()->create();
        $otherCompany = \App\Modules\Company\Models\Company::factory()->create();

        $basePayload = [
            'employee_number' => $employee->employee_number,
            'user_id' => $employee->user_id,
            'join_date' => $employee->join_date->toDateString(),
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'gender' => $employee->gender,
        ];

        // No-op: company_id sama seperti current -> harus lolos.
        $noop = $this->actingAs($admin)->putJson("/api/employees/{$employee->id}", $basePayload + [
            'company_id' => $employee->company_id,
        ]);
        $noop->assertOk();

        // Percobaan ubah beneran -> harus ditolak.
        $blocked = $this->actingAs($admin)->putJson("/api/employees/{$employee->id}", $basePayload + [
            'company_id' => $otherCompany->id,
        ]);
        $blocked->assertStatus(422);
        $blocked->assertJsonValidationErrors(['company_id']);
    }
}
