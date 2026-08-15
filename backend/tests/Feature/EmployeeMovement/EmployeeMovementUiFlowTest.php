<?php

namespace Tests\Feature\EmployeeMovement;

use App\Models\User;
use App\Modules\ApprovalFlow\Enums\ApproverType;
use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\ApprovalFlow\Models\ApprovalStep;
use App\Modules\Employee\Models\Employee;
use App\Modules\EmploymentStatus\Models\EmploymentStatus;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeMovementUiFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_can_be_filtered_by_type_and_status(): void
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
        ApprovalStep::create([
            'approval_flow_id' => $approvalFlow->id,
            'sequence' => 1,
            'approver_type' => ApproverType::SpecificEmployee->value,
            'approver_employee_id' => $employee->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->postJson("/api/employees/{$employee->id}/movements", [
            'movement_type' => 'contract_change',
            'effective_date' => now()->toDateString(),
            'contract_end_date' => now()->addMonths(6)->toDateString(),
        ])->assertStatus(201);

        $filtered = $this->actingAs($admin)->getJson('/api/employee-movements?movement_type=contract_change&status=pending_approval');
        $filtered->assertOk();
        $this->assertCount(1, $filtered->json('data.data'));

        $noMatch = $this->actingAs($admin)->getJson('/api/employee-movements?movement_type=resignation');
        $noMatch->assertOk();
        $this->assertCount(0, $noMatch->json('data.data'));
    }

    /**
     * "Change Status" UI action -> movement_type=probation_confirmed dengan
     * employment_status_id (field yang baru ditambahkan Phase 4 karena tidak
     * ada movement_type existing yang cocok untuk generic status change).
     */
    public function test_change_status_action_uses_probation_confirmed_type(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $activeStatus = EmploymentStatus::where('code', 'ACTIVE')->first()
            ?? EmploymentStatus::factory()->create(['code' => 'ACTIVE']);

        $employee = Employee::factory()->create();
        $approvalFlow = ApprovalFlow::create([
            'company_id' => $employee->company_id,
            'name' => 'Default Flow',
            'code' => 'DEFAULT-'.$employee->company_id,
            'is_active' => true,
        ]);
        ApprovalStep::create([
            'approval_flow_id' => $approvalFlow->id,
            'sequence' => 1,
            'approver_type' => ApproverType::SpecificEmployee->value,
            'approver_employee_id' => $employee->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->postJson("/api/employees/{$employee->id}/movements", [
            'movement_type' => 'probation_confirmed',
            'effective_date' => now()->toDateString(),
            'employment_status_id' => $activeStatus->id,
        ]);

        $response->assertStatus(201);
        $this->assertSame($activeStatus->id, $response->json('data.after_snapshot.employment_status_id'));
    }
}
