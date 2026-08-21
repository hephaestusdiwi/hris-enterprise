<?php

namespace Tests\Feature\Attendance;

use App\Modules\Attendance\Models\Attendance;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Attendance History (self-service): GET /api/my-attendances +
 * GET /api/my-attendances/{attendance}.
 *
 * Reuse tabel `attendances` yang sudah ada -- tidak ada tabel baru,
 * tidak ada perubahan ke ApprovalFlowResolver (fitur ini murni baca data,
 * tidak melakukan approval action apa pun).
 */
class AttendanceHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_list_own_attendance_history(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $employee->user->assignRole('employee');

        $otherEmployee = Employee::factory()->create(['company_id' => $company->id]);

        Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => now()->subDay()->toDateString(),
            'clock_in' => now()->subDay()->setTime(8, 5),
            'clock_out' => now()->subDay()->setTime(17, 2),
            'status' => 'present',
        ]);

        Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => now()->subDays(2)->toDateString(),
            'clock_in' => now()->subDays(2)->setTime(8, 20),
            'status' => 'late',
        ]);

        // Punya employee lain -- tidak boleh ikut nongol di history-nya $employee.
        Attendance::create([
            'employee_id' => $otherEmployee->id,
            'attendance_date' => now()->subDay()->toDateString(),
            'status' => 'present',
        ]);

        $response = $this->actingAs($employee->user)->getJson('/api/my-attendances');

        $response->assertOk();
        $response->assertJsonCount(2, 'data.data');
        $this->assertSame(
            [$employee->id, $employee->id],
            collect($response->json('data.data'))->pluck('employee_id')->all()
        );
    }

    public function test_employee_attendance_history_can_be_filtered_by_status_and_period(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $employee->user->assignRole('employee');

        Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => now()->subDays(10)->toDateString(),
            'status' => 'present',
        ]);

        Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => now()->subDay()->toDateString(),
            'status' => 'late',
        ]);

        $response = $this->actingAs($employee->user)->getJson('/api/my-attendances?'.http_build_query([
            'status' => 'late',
            'date_from' => now()->subDays(3)->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.status', 'late');
    }

    public function test_employee_can_view_own_attendance_detail_with_photo_url_and_location(): void
    {
        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $employee->user->assignRole('employee');

        $attendance = Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => now()->toDateString(),
            'clock_in' => now()->setTime(8, 0),
            'clock_in_latitude' => -6.2,
            'clock_in_longitude' => 106.8,
            'clock_in_method' => 'self_service',
            'status' => 'present',
            'notes' => 'Clock-in via app',
        ]);
        $attendance->clock_in_photo_path = 'attendance-photos/clock-in-'.$attendance->id.'.jpg';
        $attendance->save();

        $response = $this->actingAs($employee->user)->getJson("/api/my-attendances/{$attendance->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $attendance->id);
        $response->assertJsonPath('data.notes', 'Clock-in via app');
        $response->assertJsonPath('data.clock_in_method', 'self_service');
        $this->assertNotNull($response->json('data.clock_in_photo_url'));
        $this->assertStringContainsString(
            'attendance-photos/clock-in-'.$attendance->id.'.jpg',
            $response->json('data.clock_in_photo_url')
        );
        $this->assertNull($response->json('data.clock_out_photo_url'));
    }

    public function test_employee_cannot_view_other_employees_attendance_detail(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $employee->user->assignRole('employee');

        $otherEmployee = Employee::factory()->create(['company_id' => $company->id]);
        $otherAttendance = Attendance::create([
            'employee_id' => $otherEmployee->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'present',
        ]);

        $response = $this->actingAs($employee->user)->getJson("/api/my-attendances/{$otherAttendance->id}");

        $response->assertStatus(403);
    }

    public function test_hr_with_permission_can_filter_attendance_index_by_status(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $admin = Employee::factory()->create(['company_id' => $company->id])->user;
        $admin->assignRole('admin');

        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'present',
        ]);
        Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => now()->subDay()->toDateString(),
            'status' => 'late',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/attendances?status=late');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.status', 'late');
    }
}