<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Enums\EmployeeNonRegularInputStatus;
use App\Modules\Payroll\Models\EmployeeNonRegularInput;
use App\Modules\Payroll\Models\NonRegularPayrollComponent;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeNonRegularInputTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $admin;
    private Employee $employee;
    private NonRegularPayrollComponent $component;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->component = NonRegularPayrollComponent::create([
            'company_id' => $this->company->id, 'code' => 'BONUS', 'name' => 'Bonus Kinerja',
            'category' => 'bonus', 'is_taxable' => true, 'include_in_bpjs_base' => false,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'employee_id' => $this->employee->id,
            'non_regular_payroll_component_id' => $this->component->id,
            'payroll_period_year' => 2026,
            'payroll_period_month' => 5,
            'amount' => '2000000.00',
        ], $overrides);
    }

    public function test_can_create_bonus_input_for_employee(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/employee-non-regular-inputs', $this->payload());

        $response->assertCreated();
        $this->assertDatabaseHas('employee_non_regular_inputs', [
            'employee_id' => $this->employee->id,
            'amount' => '2000000.00',
            'is_addition' => true,
            'status' => 'draft',
        ]);
    }

    // 9. Harus mencegah duplicate processing pada payroll run yang sama.
    public function test_duplicate_input_same_employee_component_period_is_rejected(): void
    {
        $this->actingAs($this->admin)->postJson('/api/employee-non-regular-inputs', $this->payload())->assertCreated();

        $response = $this->actingAs($this->admin)->postJson('/api/employee-non-regular-inputs', $this->payload(['amount' => '999999.00']));

        $response->assertStatus(422);
        $this->assertEquals(1, EmployeeNonRegularInput::count());
    }

    public function test_different_period_is_allowed_even_for_same_employee_and_component(): void
    {
        $this->actingAs($this->admin)->postJson('/api/employee-non-regular-inputs', $this->payload(['payroll_period_month' => 5]))->assertCreated();
        $response = $this->actingAs($this->admin)->postJson('/api/employee-non-regular-inputs', $this->payload(['payroll_period_month' => 6]));

        $response->assertCreated();
        $this->assertEquals(2, EmployeeNonRegularInput::count());
    }

    public function test_can_recreate_after_voiding_the_previous_one(): void
    {
        $first = $this->actingAs($this->admin)->postJson('/api/employee-non-regular-inputs', $this->payload())->json('data.id');
        $this->actingAs($this->admin)->postJson("/api/employee-non-regular-inputs/{$first}/void", ['reason' => 'Salah input'])->assertOk();

        $response = $this->actingAs($this->admin)->postJson('/api/employee-non-regular-inputs', $this->payload(['amount' => '2500000.00']));

        $response->assertCreated();
        $this->assertEquals(2, EmployeeNonRegularInput::count());
    }

    public function test_mark_ready_transitions_from_draft(): void
    {
        $id = $this->actingAs($this->admin)->postJson('/api/employee-non-regular-inputs', $this->payload())->json('data.id');

        $response = $this->actingAs($this->admin)->postJson("/api/employee-non-regular-inputs/{$id}/mark-ready");

        $response->assertOk();
        $this->assertDatabaseHas('employee_non_regular_inputs', ['id' => $id, 'status' => 'ready']);
    }

    public function test_processed_input_cannot_be_voided(): void
    {
        $input = EmployeeNonRegularInput::create([
            ...$this->payload(),
            'is_addition' => true,
            'status' => EmployeeNonRegularInputStatus::Processed->value,
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/employee-non-regular-inputs/{$input->id}/void", ['reason' => 'coba void']);

        $response->assertStatus(422);
    }

    public function test_processed_input_cannot_be_updated(): void
    {
        $input = EmployeeNonRegularInput::create([
            ...$this->payload(),
            'is_addition' => true,
            'status' => EmployeeNonRegularInputStatus::Processed->value,
        ]);

        $response = $this->actingAs($this->admin)->putJson("/api/employee-non-regular-inputs/{$input->id}", ['amount' => '999.00']);

        $response->assertStatus(422);
    }

    public function test_adjustment_component_requires_explicit_direction(): void
    {
        $adjustment = NonRegularPayrollComponent::create([
            'company_id' => $this->company->id, 'code' => 'ADJ', 'name' => 'Adjustment',
            'category' => 'adjustment', 'is_taxable' => true, 'include_in_bpjs_base' => false,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/employee-non-regular-inputs', $this->payload([
            'non_regular_payroll_component_id' => $adjustment->id,
            // is_addition sengaja tidak diisi
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('is_addition');
    }
}
