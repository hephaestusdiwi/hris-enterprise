<?php

namespace Tests\Feature\Expense;

use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\Expense\Models\ExpenseCategory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Expense Management Phase 1 Step 1 -- Expense Category master data.
 * Semua test lewat HTTP beneran (actingAs + assignRole), bukan manggil
 * Model langsung, mengikuti pola CashAdvanceHttpEndpointsTest.
 */
class ExpenseCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): Employee
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('admin');

        return $employee;
    }

    public function test_admin_can_create_expense_category(): void
    {
        $admin = $this->actingAdmin();

        $response = $this->actingAs($admin->user)->postJson('/api/expense-categories', [
            'company_id' => $admin->company_id,
            'name' => 'Transportasi',
            'code' => 'TRANSPORT',
            'description' => 'Biaya transportasi dinas',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.code', 'TRANSPORT');

        $this->assertDatabaseHas('expense_categories', [
            'company_id' => $admin->company_id,
            'code' => 'TRANSPORT',
            'is_active' => true,
        ]);
    }

    public function test_create_expense_category_requires_valid_company(): void
    {
        $admin = $this->actingAdmin();

        $response = $this->actingAs($admin->user)->postJson('/api/expense-categories', [
            'company_id' => 999999,
            'name' => 'Transportasi',
            'code' => 'TRANSPORT',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['company_id']);
    }

    public function test_expense_category_code_must_be_unique_within_same_company(): void
    {
        $admin = $this->actingAdmin();

        ExpenseCategory::create([
            'company_id' => $admin->company_id,
            'name' => 'Transportasi',
            'code' => 'TRANSPORT',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin->user)->postJson('/api/expense-categories', [
            'company_id' => $admin->company_id,
            'name' => 'Transportasi Duplikat',
            'code' => 'TRANSPORT',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }

    public function test_same_code_is_allowed_across_different_companies(): void
    {
        $admin = $this->actingAdmin();
        $otherCompany = Company::factory()->create();

        ExpenseCategory::create([
            'company_id' => $admin->company_id,
            'name' => 'Transportasi',
            'code' => 'TRANSPORT',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin->user)->postJson('/api/expense-categories', [
            'company_id' => $otherCompany->id,
            'name' => 'Transportasi',
            'code' => 'TRANSPORT',
        ]);

        $response->assertStatus(201);

        $this->assertSame(2, ExpenseCategory::where('code', 'TRANSPORT')->count());
    }

    public function test_admin_can_update_expense_category(): void
    {
        $admin = $this->actingAdmin();

        $category = ExpenseCategory::create([
            'company_id' => $admin->company_id,
            'name' => 'Transportasi',
            'code' => 'TRANSPORT',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin->user)->putJson("/api/expense-categories/{$category->id}", [
            'company_id' => $admin->company_id,
            'name' => 'Transportasi & Akomodasi',
            'code' => 'TRANSPORT',
            'is_active' => false,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Transportasi & Akomodasi');
        $response->assertJsonPath('data.is_active', false);
    }

    public function test_expense_category_without_subcategories_can_be_deleted(): void
    {
        $admin = $this->actingAdmin();

        $category = ExpenseCategory::create([
            'company_id' => $admin->company_id,
            'name' => 'Transportasi',
            'code' => 'TRANSPORT',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin->user)->deleteJson("/api/expense-categories/{$category->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('expense_categories', ['id' => $category->id]);
    }

    public function test_expense_category_with_subcategories_cannot_be_deleted(): void
    {
        $admin = $this->actingAdmin();

        $category = ExpenseCategory::create([
            'company_id' => $admin->company_id,
            'name' => 'Transportasi',
            'code' => 'TRANSPORT',
            'is_active' => true,
        ]);

        $category->subcategories()->create([
            'name' => 'Taksi',
            'code' => 'TAXI',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin->user)->deleteJson("/api/expense-categories/{$category->id}");

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);

        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id,
            'deleted_at' => null,
        ]);
    }

    public function test_employee_without_permission_cannot_create_expense_category(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        $response = $this->actingAs($employee->user)->postJson('/api/expense-categories', [
            'company_id' => $employee->company_id,
            'name' => 'Transportasi',
            'code' => 'TRANSPORT',
        ]);

        $response->assertStatus(403);
    }

    public function test_hr_can_manage_expense_categories(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('hr');

        $response = $this->actingAs($employee->user)->postJson('/api/expense-categories', [
            'company_id' => $employee->company_id,
            'name' => 'Transportasi',
            'code' => 'TRANSPORT',
        ]);

        $response->assertStatus(201);
    }
}