<?php

namespace Tests\Feature\Expense;

use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\Expense\Models\ExpenseCategory;
use App\Modules\Expense\Models\ExpensePolicy;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Expense Management Phase 1 Step 2 -- Expense Policy master data.
 * Tidak ada test delete/soft-delete di sini dengan sengaja -- convention
 * existing (CashAdvancePolicy & ReimbursementPolicy) memang tidak punya
 * destroy() endpoint, Policy cuma dinonaktifkan lewat is_active.
 */
class ExpensePolicyTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): Employee
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('admin');

        return $employee;
    }

    private function category(int $companyId, ?string $code = null): ExpenseCategory
    {
        return ExpenseCategory::create([
            'company_id' => $companyId,
            'name' => 'Transportasi',
            'code' => $code ?? 'TRANSPORT-'.uniqid(),
            'is_active' => true,
        ]);
    }

    public function test_admin_can_create_expense_policy(): void
    {
        $admin = $this->actingAdmin();

        $response = $this->actingAs($admin->user)->postJson('/api/expense-policies', [
            'company_id' => $admin->company_id,
            'name' => 'Kebijakan Expense 2026',
            'description' => 'Kebijakan expense standar perusahaan',
            'effective_date' => now()->subMonth()->toDateString(),
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.name', 'Kebijakan Expense 2026');

        $this->assertDatabaseHas('expense_policies', [
            'company_id' => $admin->company_id,
            'name' => 'Kebijakan Expense 2026',
            'is_active' => true,
        ]);
    }

    public function test_create_requires_valid_company(): void
    {
        $admin = $this->actingAdmin();

        $response = $this->actingAs($admin->user)->postJson('/api/expense-policies', [
            'company_id' => 999999,
            'name' => 'Kebijakan Expense',
            'effective_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['company_id']);
    }

    public function test_create_attaches_categories(): void
    {
        $admin = $this->actingAdmin();
        $category = $this->category($admin->company_id);

        $response = $this->actingAs($admin->user)->postJson('/api/expense-policies', [
            'company_id' => $admin->company_id,
            'name' => 'Kebijakan Expense',
            'effective_date' => now()->toDateString(),
            'category_ids' => [$category->id],
        ]);

        $response->assertStatus(201);
        $response->assertJsonCount(1, 'data.categories');
        $response->assertJsonPath('data.categories.0.id', $category->id);
    }

    public function test_category_from_another_company_is_rejected(): void
    {
        $admin = $this->actingAdmin();
        $otherCompany = Company::factory()->create();
        $foreignCategory = $this->category($otherCompany->id);

        $response = $this->actingAs($admin->user)->postJson('/api/expense-policies', [
            'company_id' => $admin->company_id,
            'name' => 'Kebijakan Expense',
            'effective_date' => now()->toDateString(),
            'category_ids' => [$foreignCategory->id],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['category_ids.0']);

        $this->assertSame(0, ExpensePolicy::count());
    }

    public function test_category_belonging_to_same_company_is_accepted(): void
    {
        $admin = $this->actingAdmin();
        $ownCategory = $this->category($admin->company_id);

        $response = $this->actingAs($admin->user)->postJson('/api/expense-policies', [
            'company_id' => $admin->company_id,
            'name' => 'Kebijakan Expense',
            'effective_date' => now()->toDateString(),
            'category_ids' => [$ownCategory->id],
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('expense_policy_category', [
            'expense_category_id' => $ownCategory->id,
        ]);
    }

    public function test_soft_deleted_category_is_rejected(): void
    {
        $admin = $this->actingAdmin();
        $category = $this->category($admin->company_id);
        $category->delete();

        $response = $this->actingAs($admin->user)->postJson('/api/expense-policies', [
            'company_id' => $admin->company_id,
            'name' => 'Kebijakan Expense',
            'effective_date' => now()->toDateString(),
            'category_ids' => [$category->id],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['category_ids.0']);
    }

    public function test_duplicate_category_ids_are_rejected(): void
    {
        $admin = $this->actingAdmin();
        $category = $this->category($admin->company_id);

        $response = $this->actingAs($admin->user)->postJson('/api/expense-policies', [
            'company_id' => $admin->company_id,
            'name' => 'Kebijakan Expense',
            'effective_date' => now()->toDateString(),
            'category_ids' => [$category->id, $category->id],
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_update_policy(): void
    {
        $admin = $this->actingAdmin();

        $policy = ExpensePolicy::create([
            'company_id' => $admin->company_id,
            'name' => 'Kebijakan Lama',
            'effective_date' => now()->subMonth()->toDateString(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin->user)->putJson("/api/expense-policies/{$policy->id}", [
            'company_id' => $admin->company_id,
            'name' => 'Kebijakan Baru',
            'effective_date' => now()->subMonth()->toDateString(),
            'is_active' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Kebijakan Baru');
    }

    public function test_update_can_replace_category_ids(): void
    {
        $admin = $this->actingAdmin();
        $categoryA = $this->category($admin->company_id);
        $categoryB = $this->category($admin->company_id);

        $policy = ExpensePolicy::create([
            'company_id' => $admin->company_id,
            'name' => 'Kebijakan Expense',
            'effective_date' => now()->toDateString(),
            'is_active' => true,
        ]);
        $policy->categories()->sync([$categoryA->id]);

        $response = $this->actingAs($admin->user)->putJson("/api/expense-policies/{$policy->id}", [
            'company_id' => $admin->company_id,
            'name' => 'Kebijakan Expense',
            'effective_date' => now()->toDateString(),
            'is_active' => true,
            'category_ids' => [$categoryB->id],
        ]);

        $response->assertStatus(200);

        $ids = $policy->fresh()->categories()->pluck('expense_categories.id')->all();
        $this->assertSame([$categoryB->id], $ids);
    }

    public function test_update_without_category_ids_preserves_existing_categories(): void
    {
        $admin = $this->actingAdmin();
        $category = $this->category($admin->company_id);

        $policy = ExpensePolicy::create([
            'company_id' => $admin->company_id,
            'name' => 'Kebijakan Expense',
            'effective_date' => now()->toDateString(),
            'is_active' => true,
        ]);
        $policy->categories()->sync([$category->id]);

        $response = $this->actingAs($admin->user)->putJson("/api/expense-policies/{$policy->id}", [
            'company_id' => $admin->company_id,
            'name' => 'Kebijakan Expense Diperbarui',
            'effective_date' => now()->toDateString(),
            'is_active' => true,
        ]);

        $response->assertStatus(200);

        $ids = $policy->fresh()->categories()->pluck('expense_categories.id')->all();
        $this->assertSame([$category->id], $ids);
    }

    public function test_policy_can_be_activated_and_deactivated(): void
    {
        $admin = $this->actingAdmin();

        $policy = ExpensePolicy::create([
            'company_id' => $admin->company_id,
            'name' => 'Kebijakan Expense',
            'effective_date' => now()->subMonth()->toDateString(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin->user)->putJson("/api/expense-policies/{$policy->id}", [
            'company_id' => $admin->company_id,
            'name' => 'Kebijakan Expense',
            'effective_date' => now()->subMonth()->toDateString(),
            'is_active' => false,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.is_active', false);
        $this->assertFalse($policy->fresh()->isCurrentlyEffective());
    }

    public function test_is_currently_effective_returns_true_for_active_current_policy(): void
    {
        $admin = $this->actingAdmin();

        $policy = ExpensePolicy::create([
            'company_id' => $admin->company_id,
            'name' => 'Kebijakan Expense',
            'effective_date' => now()->subMonth()->toDateString(),
            'expiration_date' => now()->addMonth()->toDateString(),
            'is_active' => true,
        ]);

        $this->assertTrue($policy->isCurrentlyEffective());
    }

    public function test_expired_policy_is_not_currently_effective(): void
    {
        $admin = $this->actingAdmin();

        $policy = ExpensePolicy::create([
            'company_id' => $admin->company_id,
            'name' => 'Kebijakan Expense',
            'effective_date' => now()->subMonths(3)->toDateString(),
            'expiration_date' => now()->subMonth()->toDateString(),
            'is_active' => true,
        ]);

        $this->assertFalse($policy->isCurrentlyEffective());
    }

    public function test_expiration_date_cannot_precede_effective_date(): void
    {
        $admin = $this->actingAdmin();

        $response = $this->actingAs($admin->user)->postJson('/api/expense-policies', [
            'company_id' => $admin->company_id,
            'name' => 'Kebijakan Expense',
            'effective_date' => now()->toDateString(),
            'expiration_date' => now()->subDay()->toDateString(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['expiration_date']);
    }

    public function test_employee_without_permission_gets_403(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        $response = $this->actingAs($employee->user)->postJson('/api/expense-policies', [
            'company_id' => $employee->company_id,
            'name' => 'Kebijakan Expense',
            'effective_date' => now()->toDateString(),
        ]);

        $response->assertStatus(403);
    }

    public function test_hr_can_manage_expense_policies(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('hr');

        $response = $this->actingAs($employee->user)->postJson('/api/expense-policies', [
            'company_id' => $employee->company_id,
            'name' => 'Kebijakan Expense',
            'effective_date' => now()->toDateString(),
        ]);

        $response->assertStatus(201);
    }

    public function test_show_returns_categories(): void
    {
        $admin = $this->actingAdmin();
        $category = $this->category($admin->company_id);

        $policy = ExpensePolicy::create([
            'company_id' => $admin->company_id,
            'name' => 'Kebijakan Expense',
            'effective_date' => now()->toDateString(),
            'is_active' => true,
        ]);
        $policy->categories()->sync([$category->id]);

        $response = $this->actingAs($admin->user)->getJson("/api/expense-policies/{$policy->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.categories');
    }

    public function test_index_returns_policies(): void
    {
        $admin = $this->actingAdmin();

        ExpensePolicy::create([
            'company_id' => $admin->company_id,
            'name' => 'Kebijakan A',
            'effective_date' => now()->toDateString(),
            'is_active' => true,
        ]);

        ExpensePolicy::create([
            'company_id' => $admin->company_id,
            'name' => 'Kebijakan B',
            'effective_date' => now()->toDateString(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin->user)->getJson('/api/expense-policies');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }
}