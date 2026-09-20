<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use App\Modules\Company\Models\Company;
use App\Modules\Payroll\Models\NonRegularPayrollComponent;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NonRegularPayrollComponentTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    // 5. Non-regular component dapat dibuat.
    public function test_admin_can_create_bonus_component(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/non-regular-payroll-components', [
            'company_id' => $this->company->id,
            'code' => 'BONUS-TAHUNAN',
            'name' => 'Bonus Tahunan',
            'category' => 'bonus',
            'is_taxable' => true,
            'include_in_bpjs_base' => false,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('non_regular_payroll_components', [
            'company_id' => $this->company->id,
            'code' => 'BONUS-TAHUNAN',
            'category' => 'bonus',
        ]);
    }

    public function test_duplicate_code_within_same_company_is_rejected(): void
    {
        NonRegularPayrollComponent::create([
            'company_id' => $this->company->id, 'code' => 'BONUS-X', 'name' => 'Bonus X',
            'category' => 'bonus', 'is_taxable' => true, 'include_in_bpjs_base' => false,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/non-regular-payroll-components', [
            'company_id' => $this->company->id,
            'code' => 'BONUS-X',
            'name' => 'Bonus X Duplikat',
            'category' => 'bonus',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('code');
    }

    public function test_adjustment_category_can_be_explicitly_deduction(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/non-regular-payroll-components', [
            'company_id' => $this->company->id,
            'code' => 'ADJ-KURANG',
            'name' => 'Adjustment Pengurang',
            'category' => 'adjustment',
            'is_addition' => false,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('non_regular_payroll_components', ['code' => 'ADJ-KURANG', 'is_addition' => false]);
    }

    public function test_bonus_category_cannot_be_forced_as_deduction(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/non-regular-payroll-components', [
            'company_id' => $this->company->id,
            'code' => 'BONUS-SALAH',
            'name' => 'Bonus Salah Arah',
            'category' => 'bonus',
            'is_addition' => false, // Bonus HARUS earning, ini salah
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('is_addition');
    }

    public function test_can_update_and_deactivate_component(): void
    {
        $component = NonRegularPayrollComponent::create([
            'company_id' => $this->company->id, 'code' => 'INS-01', 'name' => 'Insentif Sales',
            'category' => 'incentive', 'is_taxable' => true, 'include_in_bpjs_base' => false,
        ]);

        $response = $this->actingAs($this->admin)->putJson("/api/non-regular-payroll-components/{$component->id}", [
            'company_id' => $this->company->id,
            'code' => 'INS-01',
            'name' => 'Insentif Sales Q2',
            'category' => 'incentive',
            'is_active' => false,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('non_regular_payroll_components', ['id' => $component->id, 'name' => 'Insentif Sales Q2', 'is_active' => false]);
    }
}
