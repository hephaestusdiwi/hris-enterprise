<?php

namespace Tests\Feature\Expense;

use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\Expense\Models\ExpenseCategory;
use App\Modules\Expense\Models\ExpenseSubcategory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseSubcategoryTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): Employee
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('admin');

        return $employee;
    }

    private function category(?int $companyId = null): ExpenseCategory
    {
        return ExpenseCategory::create([
            'company_id' => $companyId ?? Company::factory()->create()->id,
            'name' => 'Transportasi',
            'code' => 'TRANSPORT-'.uniqid(),
            'is_active' => true,
        ]);
    }

    public function test_admin_can_create_subcategory_under_category(): void
    {
        $admin = $this->actingAdmin();
        $category = $this->category($admin->company_id);

        $response = $this->actingAs($admin->user)->postJson('/api/expense-subcategories', [
            'expense_category_id' => $category->id,
            'name' => 'Taksi',
            'code' => 'TAXI',
            'description' => 'Naik taksi untuk dinas',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.code', 'TAXI');

        $this->assertDatabaseHas('expense_subcategories', [
            'expense_category_id' => $category->id,
            'code' => 'TAXI',
            'is_active' => true,
        ]);
    }

    public function test_subcategory_requires_an_existing_category(): void
    {
        $admin = $this->actingAdmin();

        $response = $this->actingAs($admin->user)->postJson('/api/expense-subcategories', [
            'expense_category_id' => 999999,
            'name' => 'Taksi',
            'code' => 'TAXI',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['expense_category_id']);
    }

    public function test_subcategory_code_must_be_unique_within_same_category(): void
    {
        $admin = $this->actingAdmin();
        $category = $this->category($admin->company_id);

        ExpenseSubcategory::create([
            'expense_category_id' => $category->id,
            'name' => 'Taksi',
            'code' => 'TAXI',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin->user)->postJson('/api/expense-subcategories', [
            'expense_category_id' => $category->id,
            'name' => 'Taksi Online',
            'code' => 'TAXI',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }

    public function test_same_code_is_allowed_across_different_categories(): void
    {
        $admin = $this->actingAdmin();
        $categoryA = $this->category($admin->company_id);
        $categoryB = $this->category($admin->company_id);

        ExpenseSubcategory::create([
            'expense_category_id' => $categoryA->id,
            'name' => 'Taksi',
            'code' => 'TAXI',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin->user)->postJson('/api/expense-subcategories', [
            'expense_category_id' => $categoryB->id,
            'name' => 'Taksi',
            'code' => 'TAXI',
        ]);

        $response->assertStatus(201);
    }

    public function test_subcategory_always_inherits_company_from_its_own_category(): void
    {
        $admin = $this->actingAdmin();
        $companyA = Company::find($admin->company_id);
        $companyB = Company::factory()->create();

        $categoryA = $this->category($companyA->id);
        $categoryB = $this->category($companyB->id);

        $subcategoryA = ExpenseSubcategory::create([
            'expense_category_id' => $categoryA->id,
            'name' => 'Taksi',
            'code' => 'TAXI',
            'is_active' => true,
        ]);

        $subcategoryB = ExpenseSubcategory::create([
            'expense_category_id' => $categoryB->id,
            'name' => 'Taksi',
            'code' => 'TAXI',
            'is_active' => true,
        ]);

        // Tidak ada cara subcategory "bocor" ke company lain -- company
        // selalu ikut kategori induknya masing-masing, tidak pernah tertukar.
        $this->assertSame($companyA->id, $subcategoryA->fresh()->category->company_id);
        $this->assertSame($companyB->id, $subcategoryB->fresh()->category->company_id);
        $this->assertNotSame(
            $subcategoryA->fresh()->category->company_id,
            $subcategoryB->fresh()->category->company_id,
        );
    }

    public function test_listing_can_be_filtered_by_category(): void
    {
        $admin = $this->actingAdmin();
        $categoryA = $this->category($admin->company_id);
        $categoryB = $this->category($admin->company_id);

        ExpenseSubcategory::create([
            'expense_category_id' => $categoryA->id,
            'name' => 'Taksi',
            'code' => 'TAXI',
            'is_active' => true,
        ]);

        ExpenseSubcategory::create([
            'expense_category_id' => $categoryB->id,
            'name' => 'Hotel',
            'code' => 'HOTEL',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin->user)
            ->getJson("/api/expense-subcategories?expense_category_id={$categoryA->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.code', 'TAXI');
    }

    public function test_admin_can_update_subcategory(): void
    {
        $admin = $this->actingAdmin();
        $category = $this->category($admin->company_id);

        $subcategory = ExpenseSubcategory::create([
            'expense_category_id' => $category->id,
            'name' => 'Taksi',
            'code' => 'TAXI',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin->user)->putJson("/api/expense-subcategories/{$subcategory->id}", [
            'expense_category_id' => $category->id,
            'name' => 'Taksi Online',
            'code' => 'TAXI',
            'is_active' => false,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Taksi Online');
        $response->assertJsonPath('data.is_active', false);
    }

    public function test_admin_can_delete_subcategory(): void
    {
        $admin = $this->actingAdmin();
        $category = $this->category($admin->company_id);

        $subcategory = ExpenseSubcategory::create([
            'expense_category_id' => $category->id,
            'name' => 'Taksi',
            'code' => 'TAXI',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin->user)->deleteJson("/api/expense-subcategories/{$subcategory->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('expense_subcategories', ['id' => $subcategory->id]);
    }

    public function test_employee_without_permission_cannot_create_subcategory(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        $category = $this->category($employee->company_id);

        $response = $this->actingAs($employee->user)->postJson('/api/expense-subcategories', [
            'expense_category_id' => $category->id,
            'name' => 'Taksi',
            'code' => 'TAXI',
        ]);

        $response->assertStatus(403);
    }
}