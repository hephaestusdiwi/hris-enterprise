<?php

namespace Tests\Feature\Expense;

use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\Expense\Models\ExpenseCategory;
use App\Modules\Expense\Models\ExpensePolicy;
use App\Modules\Expense\Models\ExpensePolicyAssignment;
use App\Modules\Expense\Services\ExpensePolicyAssignmentResolver;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Expense Management Phase 1 Step 3 -- Policy Assignment & Limit.
 * Bagian Assignment/Limit/Authorization: HTTP murni (actingAs).
 * Bagian Resolution: tidak ada endpoint publik untuk resolver di STEP
 * ini (sengaja ditunda), jadi dites langsung lewat
 * ExpensePolicyAssignmentResolver -- satu-satunya cara menguji logic ini.
 */
class ExpensePolicyAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): Employee
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('admin');

        return $employee;
    }

    private function policy(int $companyId, array $overrides = []): ExpensePolicy
    {
        return ExpensePolicy::create(array_merge([
            'company_id' => $companyId,
            'name' => 'Kebijakan Expense',
            'effective_date' => now()->subYear()->toDateString(),
            'is_active' => true,
        ], $overrides));
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

    // =========================================================
    // ASSIGNMENT
    // =========================================================

    public function test_admin_can_assign_policy_to_employee(): void
    {
        $admin = $this->actingAdmin();
        $employee = Employee::factory()->create(['company_id' => $admin->company_id]);
        $policy = $this->policy($admin->company_id);

        $response = $this->actingAs($admin->user)->postJson('/api/expense-policy-assignments', [
            'employee_id' => $employee->id,
            'expense_policy_id' => $policy->id,
            'effective_date' => now()->toDateString(),
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('expense_policy_assignments', [
            'employee_id' => $employee->id,
            'expense_policy_id' => $policy->id,
            'is_active' => true,
        ]);
    }

    public function test_hr_can_assign_policy(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $hr = Employee::factory()->create();
        $hr->user->assignRole('hr');

        $employee = Employee::factory()->create(['company_id' => $hr->company_id]);
        $policy = $this->policy($hr->company_id);

        $response = $this->actingAs($hr->user)->postJson('/api/expense-policy-assignments', [
            'employee_id' => $employee->id,
            'expense_policy_id' => $policy->id,
            'effective_date' => now()->toDateString(),
        ]);

        $response->assertStatus(201);
    }

    public function test_employee_cannot_assign_policy(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        $policy = $this->policy($employee->company_id);

        $response = $this->actingAs($employee->user)->postJson('/api/expense-policy-assignments', [
            'employee_id' => $employee->id,
            'expense_policy_id' => $policy->id,
            'effective_date' => now()->toDateString(),
        ]);

        $response->assertStatus(403);
    }

    public function test_cross_company_employee_and_policy_is_rejected(): void
    {
        $admin = $this->actingAdmin();
        $otherCompany = Company::factory()->create();

        $employee = Employee::factory()->create(['company_id' => $admin->company_id]);
        $foreignPolicy = $this->policy($otherCompany->id);

        $response = $this->actingAs($admin->user)->postJson('/api/expense-policy-assignments', [
            'employee_id' => $employee->id,
            'expense_policy_id' => $foreignPolicy->id,
            'effective_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['expense_policy_id']);
    }

    public function test_duplicate_employee_and_effective_date_is_rejected(): void
    {
        $admin = $this->actingAdmin();
        $employee = Employee::factory()->create(['company_id' => $admin->company_id]);
        $policy = $this->policy($admin->company_id);
        $sameDate = now()->toDateString();

        ExpensePolicyAssignment::create([
            'employee_id' => $employee->id,
            'expense_policy_id' => $policy->id,
            'effective_date' => $sameDate,
            'is_active' => true,
        ]);

        $anotherPolicy = $this->policy($admin->company_id);

        $response = $this->actingAs($admin->user)->postJson('/api/expense-policy-assignments', [
            'employee_id' => $employee->id,
            'expense_policy_id' => $anotherPolicy->id,
            'effective_date' => $sameDate,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['effective_date']);
    }

    public function test_assignment_can_be_updated(): void
    {
        $admin = $this->actingAdmin();
        $employee = Employee::factory()->create(['company_id' => $admin->company_id]);
        $policy = $this->policy($admin->company_id);

        $assignment = ExpensePolicyAssignment::create([
            'employee_id' => $employee->id,
            'expense_policy_id' => $policy->id,
            'effective_date' => now()->subMonth()->toDateString(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin->user)->putJson("/api/expense-policy-assignments/{$assignment->id}", [
            'is_active' => false,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.is_active', false);
    }

    public function test_effective_date_cannot_be_changed_via_update(): void
    {
        $admin = $this->actingAdmin();
        $employee = Employee::factory()->create(['company_id' => $admin->company_id]);
        $policy = $this->policy($admin->company_id);

        $originalDate = now()->subMonth()->toDateString();

        $assignment = ExpensePolicyAssignment::create([
            'employee_id' => $employee->id,
            'expense_policy_id' => $policy->id,
            'effective_date' => $originalDate,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin->user)->putJson("/api/expense-policy-assignments/{$assignment->id}", [
            'is_active' => true,
            'effective_date' => now()->toDateString(),
        ]);

        $response->assertStatus(200);

        // Field effective_date tidak dideklarasikan di UpdateRequest -->
        // dikirim atau tidak, tidak akan pernah masuk validated(), jadi
        // tidak pernah benar-benar diubah.
        $this->assertSame($originalDate, $assignment->fresh()->effective_date->toDateString());
    }

    public function test_expiration_date_works(): void
    {
        $admin = $this->actingAdmin();
        $employee = Employee::factory()->create(['company_id' => $admin->company_id]);
        $policy = $this->policy($admin->company_id);

        $assignment = ExpensePolicyAssignment::create([
            'employee_id' => $employee->id,
            'expense_policy_id' => $policy->id,
            'effective_date' => now()->subMonth()->toDateString(),
            'is_active' => true,
        ]);

        $expirationDate = now()->addMonth()->toDateString();

        $response = $this->actingAs($admin->user)->putJson("/api/expense-policy-assignments/{$assignment->id}", [
            'is_active' => true,
            'expiration_date' => $expirationDate,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.expiration_date', $expirationDate);
    }

    // =========================================================
    // RESOLUTION (lewat resolver langsung, belum ada endpoint publik)
    // =========================================================

    public function test_active_current_assignment_resolves(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $policy = $this->policy($company->id);

        ExpensePolicyAssignment::create([
            'employee_id' => $employee->id,
            'expense_policy_id' => $policy->id,
            'effective_date' => now()->subMonth()->toDateString(),
            'is_active' => true,
        ]);

        $resolved = app(ExpensePolicyAssignmentResolver::class)
            ->resolveActivePolicy($employee, now());

        $this->assertNotNull($resolved);
        $this->assertSame($policy->id, $resolved->id);
    }

    public function test_inactive_assignment_does_not_resolve(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $policy = $this->policy($company->id);

        ExpensePolicyAssignment::create([
            'employee_id' => $employee->id,
            'expense_policy_id' => $policy->id,
            'effective_date' => now()->subMonth()->toDateString(),
            'is_active' => false,
        ]);

        $resolved = app(ExpensePolicyAssignmentResolver::class)
            ->resolveActivePolicy($employee, now());

        $this->assertNull($resolved);
    }

    public function test_future_assignment_does_not_resolve(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $policy = $this->policy($company->id);

        ExpensePolicyAssignment::create([
            'employee_id' => $employee->id,
            'expense_policy_id' => $policy->id,
            'effective_date' => now()->addMonth()->toDateString(),
            'is_active' => true,
        ]);

        $resolved = app(ExpensePolicyAssignmentResolver::class)
            ->resolveActivePolicy($employee, now());

        $this->assertNull($resolved);
    }

    public function test_expired_assignment_does_not_resolve(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $policy = $this->policy($company->id);

        ExpensePolicyAssignment::create([
            'employee_id' => $employee->id,
            'expense_policy_id' => $policy->id,
            'effective_date' => now()->subMonths(3)->toDateString(),
            'expiration_date' => now()->subMonth()->toDateString(),
            'is_active' => true,
        ]);

        $resolved = app(ExpensePolicyAssignmentResolver::class)
            ->resolveActivePolicy($employee, now());

        $this->assertNull($resolved);
    }

    public function test_later_assignment_wins_over_earlier_assignment(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $policyA = $this->policy($company->id, ['name' => 'Policy A']);
        $policyB = $this->policy($company->id, ['name' => 'Policy B']);

        ExpensePolicyAssignment::create([
            'employee_id' => $employee->id,
            'expense_policy_id' => $policyA->id,
            'effective_date' => '2026-01-01',
            'expiration_date' => '2026-06-30',
            'is_active' => true,
        ]);

        ExpensePolicyAssignment::create([
            'employee_id' => $employee->id,
            'expense_policy_id' => $policyB->id,
            'effective_date' => '2026-07-01',
            'is_active' => true,
        ]);

        $resolver = app(ExpensePolicyAssignmentResolver::class);

        $resolvedMidJune = $resolver->resolveActivePolicy($employee, \Carbon\Carbon::parse('2026-06-15'));
        $resolvedMidJuly = $resolver->resolveActivePolicy($employee, \Carbon\Carbon::parse('2026-07-15'));

        $this->assertSame($policyA->id, $resolvedMidJune->id);
        $this->assertSame($policyB->id, $resolvedMidJuly->id);
    }

    public function test_inactive_or_expired_policy_prevents_resolution_even_with_valid_assignment(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        // Assignment-nya sendiri valid, tapi Policy-nya sudah expired.
        $expiredPolicy = $this->policy($company->id, [
            'effective_date' => now()->subMonths(3)->toDateString(),
            'expiration_date' => now()->subMonth()->toDateString(),
        ]);

        ExpensePolicyAssignment::create([
            'employee_id' => $employee->id,
            'expense_policy_id' => $expiredPolicy->id,
            'effective_date' => now()->subMonths(3)->toDateString(),
            'is_active' => true,
        ]);

        $resolved = app(ExpensePolicyAssignmentResolver::class)
            ->resolveActivePolicy($employee, now());

        $this->assertNull($resolved);
    }

    // =========================================================
    // LIMITS (lewat endpoint ExpensePolicy yang sudah ada)
    // =========================================================

    public function test_category_limit_can_be_stored(): void
    {
        $admin = $this->actingAdmin();
        $category = $this->category($admin->company_id);

        $response = $this->actingAs($admin->user)->postJson('/api/expense-policies', [
            'company_id' => $admin->company_id,
            'name' => 'Kebijakan Expense',
            'effective_date' => now()->toDateString(),
            'category_ids' => [$category->id],
            'category_limits' => [
                ['expense_category_id' => $category->id, 'limit_amount' => 500000],
            ],
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('expense_policy_category', [
            'expense_category_id' => $category->id,
            'limit_amount' => 500000,
        ]);
    }

    public function test_null_limit_means_unlimited(): void
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

        $this->assertDatabaseHas('expense_policy_category', [
            'expense_category_id' => $category->id,
            'limit_amount' => null,
        ]);
    }

    public function test_negative_limit_is_rejected(): void
    {
        $admin = $this->actingAdmin();
        $category = $this->category($admin->company_id);

        $response = $this->actingAs($admin->user)->postJson('/api/expense-policies', [
            'company_id' => $admin->company_id,
            'name' => 'Kebijakan Expense',
            'effective_date' => now()->toDateString(),
            'category_ids' => [$category->id],
            'category_limits' => [
                ['expense_category_id' => $category->id, 'limit_amount' => -100],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['category_limits.0.limit_amount']);
    }

    public function test_duplicate_category_limits_are_rejected(): void
    {
        $admin = $this->actingAdmin();
        $category = $this->category($admin->company_id);

        $response = $this->actingAs($admin->user)->postJson('/api/expense-policies', [
            'company_id' => $admin->company_id,
            'name' => 'Kebijakan Expense',
            'effective_date' => now()->toDateString(),
            'category_ids' => [$category->id],
            'category_limits' => [
                ['expense_category_id' => $category->id, 'limit_amount' => 500000],
                ['expense_category_id' => $category->id, 'limit_amount' => 200000],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_category_limit_for_category_not_attached_to_policy_is_rejected(): void
    {
        $admin = $this->actingAdmin();
        $attachedCategory = $this->category($admin->company_id);
        $notAttachedCategory = $this->category($admin->company_id);

        $response = $this->actingAs($admin->user)->postJson('/api/expense-policies', [
            'company_id' => $admin->company_id,
            'name' => 'Kebijakan Expense',
            'effective_date' => now()->toDateString(),
            'category_ids' => [$attachedCategory->id],
            'category_limits' => [
                ['expense_category_id' => $notAttachedCategory->id, 'limit_amount' => 500000],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['category_limits.0.expense_category_id']);
    }

    public function test_existing_category_ids_payload_remains_valid(): void
    {
        $admin = $this->actingAdmin();
        $category = $this->category($admin->company_id);

        // Payload STEP 2 lama, tanpa category_limits sama sekali.
        $response = $this->actingAs($admin->user)->postJson('/api/expense-policies', [
            'company_id' => $admin->company_id,
            'name' => 'Kebijakan Expense',
            'effective_date' => now()->toDateString(),
            'category_ids' => [$category->id],
        ]);

        $response->assertStatus(201);
        $response->assertJsonCount(1, 'data.categories');
    }

    public function test_category_ids_update_preserves_existing_limits_when_categories_remain(): void
    {
        $admin = $this->actingAdmin();
        $categoryA = $this->category($admin->company_id);
        $categoryB = $this->category($admin->company_id);

        $policy = $this->policy($admin->company_id);
        $policy->categories()->sync([
            $categoryA->id => ['limit_amount' => 500000],
            $categoryB->id => ['limit_amount' => 200000],
        ]);

        // Update cuma resend category_ids (keduanya tetap), tanpa
        // category_limits -- limit_amount masing-masing harus tetap.
        $response = $this->actingAs($admin->user)->putJson("/api/expense-policies/{$policy->id}", [
            'company_id' => $admin->company_id,
            'name' => 'Kebijakan Expense',
            'effective_date' => now()->toDateString(),
            'is_active' => true,
            'category_ids' => [$categoryA->id, $categoryB->id],
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('expense_policy_category', [
            'expense_category_id' => $categoryA->id,
            'limit_amount' => 500000,
        ]);
        $this->assertDatabaseHas('expense_policy_category', [
            'expense_category_id' => $categoryB->id,
            'limit_amount' => 200000,
        ]);
    }

    public function test_detached_category_removes_its_pivot_and_limit(): void
    {
        $admin = $this->actingAdmin();
        $categoryA = $this->category($admin->company_id);
        $categoryB = $this->category($admin->company_id);

        $policy = $this->policy($admin->company_id);
        $policy->categories()->sync([
            $categoryA->id => ['limit_amount' => 500000],
            $categoryB->id => ['limit_amount' => 200000],
        ]);

        // categoryB tidak disebut lagi -> harus ke-detach.
        $response = $this->actingAs($admin->user)->putJson("/api/expense-policies/{$policy->id}", [
            'company_id' => $admin->company_id,
            'name' => 'Kebijakan Expense',
            'effective_date' => now()->toDateString(),
            'is_active' => true,
            'category_ids' => [$categoryA->id],
        ]);

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.categories');

        $this->assertDatabaseMissing('expense_policy_category', [
            'expense_policy_id' => $policy->id,
            'expense_category_id' => $categoryB->id,
        ]);
    }

    // =========================================================
    // AUTHORIZATION
    // =========================================================

    public function test_view_permission_required_for_listing_and_show(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        $response = $this->actingAs($employee->user)->getJson('/api/expense-policy-assignments');

        $response->assertStatus(403);
    }

    public function test_create_permission_required_for_assignment_creation(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        $policy = $this->policy($employee->company_id);

        $response = $this->actingAs($employee->user)->postJson('/api/expense-policy-assignments', [
            'employee_id' => $employee->id,
            'expense_policy_id' => $policy->id,
            'effective_date' => now()->toDateString(),
        ]);

        $response->assertStatus(403);
    }

    public function test_edit_permission_required_for_assignment_update(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = Employee::factory()->create();
        $admin->user->assignRole('admin');

        $employee = Employee::factory()->create(['company_id' => $admin->company_id]);
        $policy = $this->policy($admin->company_id);

        $assignment = ExpensePolicyAssignment::create([
            'employee_id' => $employee->id,
            'expense_policy_id' => $policy->id,
            'effective_date' => now()->toDateString(),
            'is_active' => true,
        ]);

        $employee->user->assignRole('employee');

        $response = $this->actingAs($employee->user)->putJson("/api/expense-policy-assignments/{$assignment->id}", [
            'is_active' => false,
        ]);

        $response->assertStatus(403);
    }
}