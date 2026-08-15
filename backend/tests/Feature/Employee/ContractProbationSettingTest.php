<?php

namespace Tests\Feature\Employee;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Services\ContractProbationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractProbationSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_setting_and_it_affects_detection(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $employee = Employee::factory()->create([
            'contract_end_date' => now()->addDays(20)->toDateString(),
        ]);

        // Default threshold 30 hari -> employee ini kedeteksi.
        $before = app(ContractProbationService::class)->upcoming($admin);
        $this->assertTrue($before->contains(fn ($i) => $i['employee']->id === $employee->id));

        // Admin persempit threshold jadi 10 hari lewat setting.
        $response = $this->actingAs($admin)->putJson('/api/contract-probation-settings', [
            'contract_reminder_days' => 10,
            'probation_reminder_days' => 30,
            'email_reminder_enabled' => true,
            'manager_reminder_enabled' => true,
        ]);
        $response->assertOk();

        // Sekarang employee (remaining 20 hari) TIDAK lagi kedeteksi karena threshold sudah 10.
        $after = app(ContractProbationService::class)->upcoming($admin);
        $this->assertFalse($after->contains(fn ($i) => $i['employee']->id === $employee->id));
    }

    public function test_employee_role_cannot_update_setting(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('employee');

        $response = $this->actingAs($user)->putJson('/api/contract-probation-settings', [
            'contract_reminder_days' => 10,
            'probation_reminder_days' => 10,
            'email_reminder_enabled' => true,
            'manager_reminder_enabled' => true,
        ]);

        $response->assertForbidden();
    }
}
