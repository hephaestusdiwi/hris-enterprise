<?php

namespace Tests\Feature\Employee;

use App\Models\User;
use App\Modules\Employee\Contracts\ContractProbationServiceInterface;
use App\Modules\Employee\Models\Employee;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractProbationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContractProbationServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ContractProbationServiceInterface::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_contract_within_threshold_is_detected(): void
    {
        $employee = Employee::factory()->create([
            'contract_end_date' => now()->addDays(18)->toDateString(),
        ]);

        $result = $this->service->upcoming($this->admin(), contractThresholdDays: 30);

        $this->assertTrue($result->contains(fn ($i) => $i['type'] === 'contract' && $i['employee']->id === $employee->id));
        $item = $result->firstWhere('employee.id', $employee->id);
        $this->assertSame(18, $item['remaining_days']);
    }

    public function test_contract_outside_threshold_is_not_detected(): void
    {
        Employee::factory()->create([
            'contract_end_date' => now()->addDays(45)->toDateString(),
        ]);

        $result = $this->service->upcoming($this->admin(), contractThresholdDays: 30);

        $this->assertCount(0, $result);
    }

    public function test_probation_within_threshold_is_detected(): void
    {
        $employee = Employee::factory()->create([
            'probation_end_date' => now()->addDays(7)->toDateString(),
        ]);

        $result = $this->service->upcoming($this->admin(), probationThresholdDays: 30);

        $this->assertTrue($result->contains(fn ($i) => $i['type'] === 'probation' && $i['employee']->id === $employee->id));
    }

    public function test_expired_contract_is_not_treated_as_upcoming(): void
    {
        Employee::factory()->create([
            'contract_end_date' => now()->subDays(5)->toDateString(),
        ]);

        $result = $this->service->upcoming($this->admin(), contractThresholdDays: 30);

        $this->assertCount(0, $result);
    }

    public function test_manager_cannot_see_employee_outside_hierarchy(): void
    {
        // Head -> Manager A, Manager B (sibling), masing2 punya staff dengan contract mendekati akhir
        $managerAUser = User::factory()->create();
        $managerAUser->assignRole('employee');
        $managerAUser->givePermissionTo('view employees');

        $managerA = Employee::factory()->create(['user_id' => $managerAUser->id]);
        $managerB = Employee::factory()->create();

        $staffUnderA = Employee::factory()->create([
            'manager_employee_id' => $managerA->id,
            'contract_end_date' => now()->addDays(10)->toDateString(),
        ]);
        $staffUnderB = Employee::factory()->create([
            'manager_employee_id' => $managerB->id,
            'contract_end_date' => now()->addDays(10)->toDateString(),
        ]);

        $result = $this->service->upcoming($managerAUser, contractThresholdDays: 30);
        $visibleIds = $result->pluck('employee.id')->all();

        $this->assertContains($staffUnderA->id, $visibleIds);
        $this->assertNotContains($staffUnderB->id, $visibleIds);
    }
}
