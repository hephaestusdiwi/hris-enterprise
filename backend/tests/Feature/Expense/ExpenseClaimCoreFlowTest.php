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
use App\Modules\Expense\Models\ExpensePolicy;
use App\Modules\Expense\Models\ExpensePolicyAssignment;
use App\Modules\Expense\Models\ExpenseSubcategory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Expense Management Phase 1 STEP 4A -- Core Expense Claim business flow.
 * Semua test lewat HTTP (actingAs+postJson), bukan manggil Service
 * langsung, sesuai instruksi eksplisit sesi ini.
 */
class ExpenseClaimCoreFlowTest extends TestCase
{
    use RefreshDatabase;

    private function actingEmployee(): Employee
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        return $employee;
    }

    private function category(int $companyId): ExpenseCategory
    {
        return ExpenseCategory::create([
            'company_id' => $companyId,
            'name' => 'Transportasi',
            'code' => 'TRANSPORT-'.uniqid(),
            'is_active' => true,
        ]);
    }

    private function policyWithCategory(int $companyId, ?string $limitAmount = null): array
    {
        $policy = ExpensePolicy::create([
            'company_id' => $companyId,
            'name' => 'Kebijakan Expense',
            'effective_date' => now()->subYear()->toDateString(),
            'is_active' => true,
        ]);

        $category = $this->category($companyId);
        $policy->categories()->sync([$category->id => ['limit_amount' => $limitAmount]]);

        return [$policy, $category];
    }

    private function assignPolicy(Employee $employee, ExpensePolicy $policy, ?string $effectiveDate = null): ExpensePolicyAssignment
    {
        return ExpensePolicyAssignment::create([
            'employee_id' => $employee->id,
            'expense_policy_id' => $policy->id,
            'effective_date' => $effectiveDate ?? now()->subMonth()->toDateString(),
            'is_active' => true,
        ]);
    }

    private function submitPayload(int $categoryId, string $amount = '100000', ?int $subcategoryId = null): array
    {
        return array_filter([
            'expense_category_id' => $categoryId,
            'expense_subcategory_id' => $subcategoryId,
            'expense_date' => now()->toDateString(),
            'amount' => $amount,
            'description' => 'Beli tiket transportasi dinas',
        ], fn ($v) => $v !== null);
    }

    // 1,2,3,8,21: submit sukses, identity dari auth employee, simpan
    // assignment_id, category valid diterima, auto-approve tanpa flow.
    public function test_employee_can_create_expense_claim(): void
    {
        $employee = $this->actingEmployee();
        [$policy, $category] = $this->policyWithCategory($employee->company_id);
        $assignment = $this->assignPolicy($employee, $policy);

        $response = $this->actingAs($employee->user)->postJson(
            '/api/my-expense-claims',
            $this->submitPayload($category->id)
        );

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.expense_policy_assignment_id', $assignment->id);

        $this->assertSame(1, ExpenseClaim::count());

        $claim = ExpenseClaim::first();
        $this->assertSame($employee->id, $claim->employee_id);
        $this->assertSame(
            ExpenseClaimStatus::Approved,
            $claim->status,
            'Tanpa ApprovalFlow, submit harus auto-approve.'
        );
    }

    // 4: policy resolve berdasarkan expense_date, bukan tanggal submit.
    public function test_policy_resolved_using_expense_date_not_submission_date(): void
    {
        $employee = $this->actingEmployee();

        // Policy A berlaku sampai 3 bulan lalu (sudah expired hari ini),
        // Policy B berlaku sejak 3 bulan lalu. Claim expense_date 4 bulan
        // lalu -- harus resolve ke Policy A (masih efektif di -4mo)
        // meskipun tanggal submit (hari ini) Policy A sudah expired.
        $policyA = ExpensePolicy::create([
            'company_id' => $employee->company_id,
            'name' => 'Policy Lama',
            'effective_date' => now()->subMonths(12)->toDateString(),
            'expiration_date' => now()->subMonths(3)->toDateString(),
            'is_active' => true,
        ]);
        $categoryA = $this->category($employee->company_id);
        $policyA->categories()->sync([$categoryA->id => ['limit_amount' => null]]);

        $this->assignPolicy($employee, $policyA, now()->subMonths(12)->toDateString());

        $policyB = ExpensePolicy::create([
            'company_id' => $employee->company_id,
            'name' => 'Policy Baru',
            'effective_date' => now()->subMonths(3)->toDateString(),
            'is_active' => true,
        ]);
        $categoryB = $this->category($employee->company_id);
        $policyB->categories()->sync([$categoryB->id => ['limit_amount' => null]]);

        $this->assignPolicy($employee, $policyB, now()->subMonths(3)->toDateString());

        $response = $this->actingAs($employee->user)->postJson('/api/my-expense-claims', [
            'expense_category_id' => $categoryA->id,
            'expense_date' => now()->subMonths(4)->toDateString(),
            'amount' => '50000',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.expense_policy_assignment_id', $policyA->assignments()->first()->id);
    }

    // 5: tidak ada assignment aktif -> 422.
    public function test_no_active_policy_assignment_returns_422(): void
    {
        $employee = $this->actingEmployee();
        [, $category] = $this->policyWithCategory($employee->company_id);
        // Sengaja tidak di-assign ke employee.

        $response = $this->actingAs($employee->user)->postJson(
            '/api/my-expense-claims',
            $this->submitPayload($category->id)
        );

        $response->assertStatus(422);
    }

    // 6: policy sudah expired -> 422 (assignment-nya sendiri valid).
    public function test_expired_policy_returns_422(): void
    {
        $employee = $this->actingEmployee();

        $policy = ExpensePolicy::create([
            'company_id' => $employee->company_id,
            'name' => 'Policy Expired',
            'effective_date' => now()->subMonths(6)->toDateString(),
            'expiration_date' => now()->subMonth()->toDateString(),
            'is_active' => true,
        ]);
        $category = $this->category($employee->company_id);
        $policy->categories()->sync([$category->id => ['limit_amount' => null]]);

        $this->assignPolicy($employee, $policy, now()->subMonths(6)->toDateString());

        $response = $this->actingAs($employee->user)->postJson(
            '/api/my-expense-claims',
            $this->submitPayload($category->id)
        );

        $response->assertStatus(422);
    }

    // 7: category tidak termasuk policy -> 422.
    public function test_category_not_allowed_by_policy_returns_422(): void
    {
        $employee = $this->actingEmployee();
        [$policy] = $this->policyWithCategory($employee->company_id);
        $this->assignPolicy($employee, $policy);

        $foreignCategory = $this->category($employee->company_id);

        $response = $this->actingAs($employee->user)->postJson(
            '/api/my-expense-claims',
            $this->submitPayload($foreignCategory->id)
        );

        $response->assertStatus(422);
    }

    // 9: subcategory optional, valid -> diterima.
    public function test_optional_subcategory_is_accepted(): void
    {
        $employee = $this->actingEmployee();
        [$policy, $category] = $this->policyWithCategory($employee->company_id);
        $this->assignPolicy($employee, $policy);

        $subcategory = ExpenseSubcategory::create([
            'expense_category_id' => $category->id,
            'name' => 'Taksi',
            'code' => 'TAXI',
            'is_active' => true,
        ]);

        $response = $this->actingAs($employee->user)->postJson(
            '/api/my-expense-claims',
            $this->submitPayload($category->id, '100000', $subcategory->id)
        );

        $response->assertStatus(201);
        $response->assertJsonPath('data.expense_subcategory_id', $subcategory->id);
    }

    // 10: subcategory milik category lain -> 422.
    public function test_subcategory_belonging_to_another_category_returns_422(): void
    {
        $employee = $this->actingEmployee();
        [$policy, $category] = $this->policyWithCategory($employee->company_id);
        $this->assignPolicy($employee, $policy);

        $otherCategory = $this->category($employee->company_id);
        $foreignSubcategory = ExpenseSubcategory::create([
            'expense_category_id' => $otherCategory->id,
            'name' => 'Bensin',
            'code' => 'FUEL',
            'is_active' => true,
        ]);

        $response = $this->actingAs($employee->user)->postJson(
            '/api/my-expense-claims',
            $this->submitPayload($category->id, '100000', $foreignSubcategory->id)
        );

        $response->assertStatus(422);
    }

    // 11,12: amount di bawah/sama dengan limit -> diterima.
    public function test_amount_below_limit_is_accepted(): void
    {
        $employee = $this->actingEmployee();
        [$policy, $category] = $this->policyWithCategory($employee->company_id, '500000');
        $this->assignPolicy($employee, $policy);

        $response = $this->actingAs($employee->user)->postJson(
            '/api/my-expense-claims',
            $this->submitPayload($category->id, '300000')
        );

        $response->assertStatus(201);
    }

    public function test_amount_equal_to_limit_is_accepted(): void
    {
        $employee = $this->actingEmployee();
        [$policy, $category] = $this->policyWithCategory($employee->company_id, '500000');
        $this->assignPolicy($employee, $policy);

        $response = $this->actingAs($employee->user)->postJson(
            '/api/my-expense-claims',
            $this->submitPayload($category->id, '500000')
        );

        $response->assertStatus(201);
    }

    // 13: amount di atas limit -> 422.
    public function test_amount_above_limit_returns_422(): void
    {
        $employee = $this->actingEmployee();
        [$policy, $category] = $this->policyWithCategory($employee->company_id, '500000');
        $this->assignPolicy($employee, $policy);

        $response = $this->actingAs($employee->user)->postJson(
            '/api/my-expense-claims',
            $this->submitPayload($category->id, '500000.01')
        );

        $response->assertStatus(422);
    }

    // 14: limit NULL = unlimited, amount besar tetap diterima.
    public function test_null_limit_means_unlimited(): void
    {
        $employee = $this->actingEmployee();
        [$policy, $category] = $this->policyWithCategory($employee->company_id, null);
        $this->assignPolicy($employee, $policy);

        $response = $this->actingAs($employee->user)->postJson(
            '/api/my-expense-claims',
            $this->submitPayload($category->id, '999999999')
        );

        $response->assertStatus(201);
    }

    // 22: ada ApprovalFlow -> Pending, bukan auto-approve.
    public function test_claim_with_approval_flow_enters_pending_status(): void
    {
        $employee = $this->actingEmployee();
        $approverEmployee = Employee::factory()->create();
        [$policy, $category] = $this->policyWithCategory($employee->company_id);
        $this->assignPolicy($employee, $policy);

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

        $response = $this->actingAs($employee->user)->postJson(
            '/api/my-expense-claims',
            $this->submitPayload($category->id)
        );

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'pending');

        $claim = ExpenseClaim::first();
        $this->assertNotNull($claim->approvalRequest);
    }
}