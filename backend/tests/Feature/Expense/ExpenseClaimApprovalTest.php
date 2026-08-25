<?php

namespace Tests\Feature\Expense;

use App\Modules\ApprovalFlow\Enums\ApproverType;
use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\ApprovalFlow\Models\ApprovalFlowAssignment;
use App\Modules\ApprovalFlow\Models\ApprovalStep;
use App\Modules\Employee\Models\Employee;
use App\Modules\Expense\Enums\ExpenseClaimStatus;
use App\Modules\Expense\Models\ExpenseCategory;
use App\Modules\Expense\Models\ExpenseClaim;
use App\Modules\Expense\Models\ExpenseClaimApprovalStepDecision;
use App\Modules\Expense\Models\ExpensePolicy;
use App\Modules\Expense\Models\ExpensePolicyAssignment;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Expense Management Phase 1 STEP 4C -- Approval decide layer.
 * Pola persis CashAdvance approval test: HTTP murni, ApprovalFlow+Step
 * dibuat manual dengan approver_type=specific_employee.
 */
class ExpenseClaimApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function employeeWithClaim(): array
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        $policy = ExpensePolicy::create([
            'company_id' => $employee->company_id,
            'name' => 'Kebijakan Expense',
            'effective_date' => now()->subYear()->toDateString(),
            'is_active' => true,
        ]);

        $category = ExpenseCategory::create([
            'company_id' => $employee->company_id,
            'name' => 'Transportasi',
            'code' => 'TRANSPORT-'.uniqid(),
            'is_active' => true,
        ]);
        $policy->categories()->sync([$category->id => ['limit_amount' => null]]);

        ExpensePolicyAssignment::create([
            'employee_id' => $employee->id,
            'expense_policy_id' => $policy->id,
            'effective_date' => now()->subMonth()->toDateString(),
            'is_active' => true,
        ]);

        return [$employee, $category];
    }

    /**
     * @return array{0: Employee, 1: ExpenseClaim, 2: Employee}  [claimOwner, claim, approver]
     */
    private function claimWithSingleStepFlow(): array
    {
        [$employee, $category] = $this->employeeWithClaim();
        $approverEmployee = Employee::factory()->create();

        $flow = ApprovalFlow::create([
            'company_id' => $employee->company_id,
            'approval_type' => 'expense_claim',
            'name' => 'Expense Claim Flow',
            'code' => 'EXPENSE-CLAIM-FLOW-'.uniqid(),
            'is_active' => true,
        ]);

        ApprovalStep::create([
            'approval_flow_id' => $flow->id,
            'sequence' => 1,
            'approver_type' => ApproverType::SpecificEmployee->value,
            'approver_employee_id' => $approverEmployee->id,
            'is_active' => true,
        ]);

        ApprovalFlowAssignment::create([
            'approval_flow_id' => $flow->id,
            'employee_id' => $employee->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($employee->user)->postJson('/api/my-expense-claims', [
            'expense_category_id' => $category->id,
            'expense_date' => now()->toDateString(),
            'amount' => '100000',
        ]);

        $claim = ExpenseClaim::findOrFail($response->json('data.id'));

        return [$employee, $claim, $approverEmployee];
    }

    public function test_eligible_approver_can_approve_single_step_flow(): void
    {
        [, $claim, $approverEmployee] = $this->claimWithSingleStepFlow();

        $this->assertSame(ExpenseClaimStatus::Pending, $claim->fresh()->status);

        $decision = ExpenseClaimApprovalStepDecision::first();

        $response = $this->actingAs($approverEmployee->user)
            ->postJson("/api/expense-claim-approvals/{$decision->id}/decide", [
                'action' => 'approve',
                'notes' => 'OK, disetujui',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $this->assertSame(ExpenseClaimStatus::Approved, $claim->fresh()->status);
        $this->assertNotNull($claim->fresh()->decided_at);
    }

    public function test_eligible_approver_can_reject(): void
    {
        [, $claim, $approverEmployee] = $this->claimWithSingleStepFlow();

        $decision = ExpenseClaimApprovalStepDecision::first();

        $response = $this->actingAs($approverEmployee->user)
            ->postJson("/api/expense-claim-approvals/{$decision->id}/decide", [
                'action' => 'reject',
                'notes' => 'Bukti kurang lengkap',
            ]);

        $response->assertStatus(200);
        $this->assertSame(ExpenseClaimStatus::Rejected, $claim->fresh()->status);
    }

    public function test_duplicate_decision_on_same_step_is_rejected(): void
    {
        [, $claim, $approverEmployee] = $this->claimWithSingleStepFlow();

        $decision = ExpenseClaimApprovalStepDecision::first();

        $this->actingAs($approverEmployee->user)
            ->postJson("/api/expense-claim-approvals/{$decision->id}/decide", ['action' => 'approve']);

        $response = $this->actingAs($approverEmployee->user)
            ->postJson("/api/expense-claim-approvals/{$decision->id}/decide", ['action' => 'approve']);

        $response->assertStatus(422);
    }

    public function test_unauthorized_user_cannot_decide(): void
    {
        $this->claimWithSingleStepFlow();
        $decision = ExpenseClaimApprovalStepDecision::first();

        $stranger = Employee::factory()->create();
        $stranger->user->assignRole('employee');

        $response = $this->actingAs($stranger->user)
            ->postJson("/api/expense-claim-approvals/{$decision->id}/decide", ['action' => 'approve']);

        $response->assertStatus(422);
    }

    public function test_multi_step_flow_advances_to_next_step_on_approve(): void
    {
        [$employee, $category] = $this->employeeWithClaim();
        $approver1 = Employee::factory()->create();
        $approver2 = Employee::factory()->create();

        $flow = ApprovalFlow::create([
            'company_id' => $employee->company_id,
            'approval_type' => 'expense_claim',
            'name' => 'Expense Claim Multi Step',
            'code' => 'EXPENSE-CLAIM-MULTI-'.uniqid(),
            'is_active' => true,
        ]);

        ApprovalStep::create([
            'approval_flow_id' => $flow->id,
            'sequence' => 1,
            'approver_type' => ApproverType::SpecificEmployee->value,
            'approver_employee_id' => $approver1->id,
            'is_active' => true,
        ]);

        ApprovalStep::create([
            'approval_flow_id' => $flow->id,
            'sequence' => 2,
            'approver_type' => ApproverType::SpecificEmployee->value,
            'approver_employee_id' => $approver2->id,
            'is_active' => true,
        ]);

        ApprovalFlowAssignment::create([
            'approval_flow_id' => $flow->id,
            'employee_id' => $employee->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($employee->user)->postJson('/api/my-expense-claims', [
            'expense_category_id' => $category->id,
            'expense_date' => now()->toDateString(),
            'amount' => '100000',
        ]);
        $claim = ExpenseClaim::findOrFail($response->json('data.id'));

        $step1Decision = ExpenseClaimApprovalStepDecision::where('sequence', 1)->first();
        $step2Decision = ExpenseClaimApprovalStepDecision::where('sequence', 2)->first();

        // Approver 2 belum gilirannya -- ditolak.
        $prematureResponse = $this->actingAs($approver2->user)
            ->postJson("/api/expense-claim-approvals/{$step2Decision->id}/decide", ['action' => 'approve']);
        $prematureResponse->assertStatus(422);

        // Approver 1 approve -> lanjut ke step 2, claim tetap Pending.
        $this->actingAs($approver1->user)
            ->postJson("/api/expense-claim-approvals/{$step1Decision->id}/decide", ['action' => 'approve'])
            ->assertStatus(200);

        $this->assertSame(ExpenseClaimStatus::Pending, $claim->fresh()->status);

        // Approver 2 approve -> claim Approved.
        $this->actingAs($approver2->user)
            ->postJson("/api/expense-claim-approvals/{$step2Decision->id}/decide", ['action' => 'approve'])
            ->assertStatus(200);

        $this->assertSame(ExpenseClaimStatus::Approved, $claim->fresh()->status);
    }

    public function test_pending_decisions_for_user_shows_only_current_step_and_eligible_approver(): void
    {
        [, , $approverEmployee] = $this->claimWithSingleStepFlow();

        $response = $this->actingAs($approverEmployee->user)->getJson('/api/expense-claim-approvals');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_pending_decisions_hidden_from_ineligible_user(): void
    {
        $this->claimWithSingleStepFlow();

        $stranger = Employee::factory()->create();
        $stranger->user->assignRole('employee');

        $response = $this->actingAs($stranger->user)->getJson('/api/expense-claim-approvals');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }
}