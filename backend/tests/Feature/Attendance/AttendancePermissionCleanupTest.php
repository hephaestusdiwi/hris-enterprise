<?php

namespace Tests\Feature\Attendance;

use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * STEP D cleanup -- permission gaps yang ditemukan pas kerjain STEP A/B/C:
 *
 * D1: 'view leave types' gak pernah didefinisikan di $permissions master
 *     array RolePermissionSeeder -- endpoint GET /api/leave-types
 *     unreachable buat SEMUA role, termasuk admin. Ini yang bikin dropdown
 *     Leave Type di Absence Deduction (STEP B Fase 2) broken.
 * D2: role 'hr' belum punya 'view attendances'/'view leave balances'/
 *     'view leave types'/'view leave requests' walau konsepnya "HR
 *     attendance & leave management".
 */
class AttendancePermissionCleanupTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(Company $company): \App\Models\User
    {
        $admin = Employee::factory()->create(['company_id' => $company->id])->user;
        $admin->assignRole('admin');

        return $admin;
    }

    private function makeHr(Company $company): \App\Models\User
    {
        $hr = Employee::factory()->create(['company_id' => $company->id])->user;
        $hr->assignRole('hr');

        return $hr;
    }

    // ---------- D1 ----------

    public function test_admin_can_access_leave_types_endpoint(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        $admin = $this->makeAdmin($company);

        $response = $this->actingAs($admin)->getJson('/api/leave-types');

        $response->assertOk();
    }

    public function test_hr_can_access_leave_types_endpoint(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        $hr = $this->makeHr($company);

        $response = $this->actingAs($hr)->getJson('/api/leave-types');

        $response->assertOk();
    }

    // ---------- D2 ----------

    public function test_hr_can_access_attendance_report(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        $hr = $this->makeHr($company);

        $response = $this->actingAs($hr)->getJson('/api/attendance-report?'.http_build_query([
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-01',
        ]));

        $response->assertOk();
    }

    public function test_hr_can_access_attendances_index(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        $hr = $this->makeHr($company);

        $response = $this->actingAs($hr)->getJson('/api/attendances');

        $response->assertOk();
    }

    public function test_hr_can_access_leave_balances_endpoint(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        $hr = $this->makeHr($company);

        $response = $this->actingAs($hr)->getJson('/api/leave-balances');

        $response->assertOk();
    }

    public function test_hr_can_access_leave_requests_index(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        $hr = $this->makeHr($company);

        $response = $this->actingAs($hr)->getJson('/api/leave-requests');

        $response->assertOk();
    }

    // ---------- Regression guard: employee tetap gak boleh ----------

    public function test_employee_still_cannot_access_leave_types_or_attendance_report(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $employee->user->assignRole('employee');

        $this->actingAs($employee->user)->getJson('/api/leave-types')->assertStatus(403);
        $this->actingAs($employee->user)->getJson('/api/attendance-report?date_from=2026-03-01&date_to=2026-03-01')->assertStatus(403);
    }
}