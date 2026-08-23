<?php

namespace Tests\Feature\Attendance;

use App\Modules\ApprovalFlow\Enums\ApproverType;
use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\ApprovalFlow\Models\ApprovalStep;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Attendance\Models\AttendanceApprovalStepDecision;
use App\Modules\AttendanceRequest\Models\AttendanceRequestApprovalStepDecision;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\Shift\Models\Shift;
use App\Modules\WorkingSchedule\Models\WorkingSchedule;
use App\Modules\WorkingSchedule\Models\WorkingScheduleDetail;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * STEP A -- Attendance Activity. Semua test lewat actual application
 * flow (endpoint HTTP asli), bukan panggil AttendanceActivityService
 * langsung, sesuai instruksi.
 */
class AttendanceActivityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sama persis dengan helper di AttendanceRequestSubmissionTest /
     * AttendanceRequestApprovalFlowTest -- duplikasi kecil yang sengaja,
     * bukan diextract jadi trait supaya test tetap self-contained.
     */
    private function makeEmployeeWithShift(Company $company, Carbon $attendanceDate, array $shiftOverrides = []): Employee
    {
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $shift = Shift::create(array_merge([
            'company_id' => $company->id,
            'name' => 'Shift Pagi',
            'code' => 'SHIFT-'.$employee->id,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'is_overnight' => false,
            'late_tolerance_minutes' => 10,
            'overtime_threshold_minutes' => 30,
            'check_in_before_minutes' => 60,
            'check_out_after_minutes' => 60,
            'is_active' => true,
        ], $shiftOverrides));

        $workingSchedule = WorkingSchedule::create([
            'company_id' => $company->id,
            'name' => 'Default Schedule',
            'code' => 'WS-'.$employee->id,
            'is_active' => true,
        ]);

        WorkingScheduleDetail::create([
            'working_schedule_id' => $workingSchedule->id,
            'day_of_week' => $attendanceDate->dayOfWeekIso,
            'shift_id' => $shift->id,
        ]);

        $employee->update(['working_schedule_id' => $workingSchedule->id]);

        return $employee->fresh();
    }

    /**
     * Company-wide 1-step approval flow untuk approval_type tertentu,
     * approver = specific employee. Match ApprovalFlowResolver fallback
     * tier terakhir (job_level_id/department_id/branch_id NULL semua).
     */
    private function makeApprovalFlow(Company $company, Employee $approver, string $approvalType): ApprovalFlow
    {
        $flow = ApprovalFlow::create([
            'company_id' => $company->id,
            'name' => 'Default Flow '.$approvalType,
            'code' => 'DEFAULT-'.$approvalType.'-'.$company->id,
            'approval_type' => $approvalType,
            'is_active' => true,
        ]);

        ApprovalStep::create([
            'approval_flow_id' => $flow->id,
            'sequence' => 1,
            'approver_type' => ApproverType::SpecificEmployee->value,
            'approver_employee_id' => $approver->id,
            'is_active' => true,
        ]);

        return $flow;
    }

    // ---------- 1 & 10: clock-in creates activity, actor tersimpan ----------
    public function test_clock_in_creates_activity_with_correct_employee_and_actor(): void
    {
        $this->seed(RolePermissionSeeder::class);

        Carbon::setTestNow(Carbon::today()->setTime(8, 0));
        $employee = $this->makeEmployeeWithShift(Company::factory()->create(), Carbon::today());
        $employee->user->assignRole('employee');

        $response = $this->actingAs($employee->user)->postJson('/api/attendance/clock-in', []);
        $response->assertStatus(201);

        $this->assertDatabaseHas('attendance_activities', [
            'employee_id' => $employee->id,
            'activity_type' => 'clock_in',
            'actor_user_id' => $employee->user_id,
        ]);

        Carbon::setTestNow();
    }

    // ---------- 2: clock-out creates activity ----------
    public function test_clock_out_creates_activity(): void
    {
        $this->seed(RolePermissionSeeder::class);

        Carbon::setTestNow(Carbon::today()->setTime(8, 0));
        $employee = $this->makeEmployeeWithShift(Company::factory()->create(), Carbon::today());
        $employee->user->assignRole('employee');

        $this->actingAs($employee->user)->postJson('/api/attendance/clock-in', [])->assertStatus(201);

        Carbon::setTestNow(Carbon::today()->setTime(17, 0));
        $response = $this->actingAs($employee->user)->postJson('/api/attendance/clock-out', []);
        $response->assertStatus(200);

        $this->assertDatabaseHas('attendance_activities', [
            'employee_id' => $employee->id,
            'activity_type' => 'clock_out',
            'actor_user_id' => $employee->user_id,
        ]);

        Carbon::setTestNow();
    }

    // ---------- 3 & 11: late detection creates activity, actor NULL (system) ----------
    public function test_late_clock_in_creates_late_detected_activity_with_null_actor(): void
    {
        $this->seed(RolePermissionSeeder::class);

        // Shift mulai 08:00, tolerance 10 menit -> clock-in jam 09:00 = telat 60 menit.
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));
        $employee = $this->makeEmployeeWithShift(Company::factory()->create(), Carbon::today());
        $employee->user->assignRole('employee');

        $this->actingAs($employee->user)->postJson('/api/attendance/clock-in', [])->assertStatus(201);

        $this->assertDatabaseHas('attendance_activities', [
            'employee_id' => $employee->id,
            'activity_type' => 'late_detected',
        ]);

        // "Detected" itu system-triggered dari perhitungan clock-in, bukan
        // aksi user eksplisit -> actor harus NULL.
        $this->assertDatabaseHas('attendance_activities', [
            'employee_id' => $employee->id,
            'activity_type' => 'late_detected',
            'actor_user_id' => null,
        ]);

        Carbon::setTestNow();
    }

    // ---------- 4: overtime detection creates activity ----------
    public function test_overtime_clock_out_creates_overtime_detected_activity(): void
    {
        $this->seed(RolePermissionSeeder::class);

        Carbon::setTestNow(Carbon::today()->setTime(8, 0));
        $employee = $this->makeEmployeeWithShift(Company::factory()->create(), Carbon::today());
        $employee->user->assignRole('employee');

        $this->actingAs($employee->user)->postJson('/api/attendance/clock-in', [])->assertStatus(201);

        // Shift berakhir 17:00, threshold overtime 30 menit -> clock-out
        // 18:15 = 75 menit lembur, melebihi threshold.
        Carbon::setTestNow(Carbon::today()->setTime(18, 15));
        $this->actingAs($employee->user)->postJson('/api/attendance/clock-out', [])->assertStatus(200);

        $this->assertDatabaseHas('attendance_activities', [
            'employee_id' => $employee->id,
            'activity_type' => 'overtime_detected',
            'actor_user_id' => null,
        ]);

        Carbon::setTestNow();
    }

    // ---------- 5 & 6: Late approval submission + decision creates activity ----------
    public function test_late_approval_submission_and_decision_create_activities(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $approver = Employee::factory()->create(['company_id' => $company->id]);
        $this->makeApprovalFlow($company, $approver, 'attendance');

        Carbon::setTestNow(Carbon::today()->setTime(9, 0));
        $employee = $this->makeEmployeeWithShift($company, Carbon::today());
        $employee->user->assignRole('employee');

        $this->actingAs($employee->user)->postJson('/api/attendance/clock-in', [])->assertStatus(201);

        // Karena ada ApprovalFlow aktif untuk approval_type=attendance,
        // late detection harus masuk jalur "submitted" (bukan auto-approve).
        $this->assertDatabaseHas('attendance_activities', [
            'employee_id' => $employee->id,
            'activity_type' => 'late_approval_submitted',
            'actor_user_id' => null,
        ]);

        $decision = AttendanceApprovalStepDecision::whereHas(
            'request',
            fn ($q) => $q->where('employee_id', $employee->id)
        )->firstOrFail();

        $response = $this->actingAs($approver->user)->postJson(
            "/api/attendance-approvals/{$decision->id}/decide",
            ['action' => 'approve']
        );
        $response->assertStatus(200);

        $this->assertDatabaseHas('attendance_activities', [
            'employee_id' => $employee->id,
            'activity_type' => 'late_approved',
            'actor_user_id' => $approver->user_id,
        ]);

        Carbon::setTestNow();
    }

    public function test_late_rejection_creates_activity(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $approver = Employee::factory()->create(['company_id' => $company->id]);
        $this->makeApprovalFlow($company, $approver, 'attendance');

        Carbon::setTestNow(Carbon::today()->setTime(9, 0));
        $employee = $this->makeEmployeeWithShift($company, Carbon::today());
        $employee->user->assignRole('employee');

        $this->actingAs($employee->user)->postJson('/api/attendance/clock-in', [])->assertStatus(201);

        $decision = AttendanceApprovalStepDecision::whereHas(
            'request',
            fn ($q) => $q->where('employee_id', $employee->id)
        )->firstOrFail();

        $this->actingAs($approver->user)->postJson(
            "/api/attendance-approvals/{$decision->id}/decide",
            ['action' => 'reject', 'notes' => 'Tidak ada alasan yang valid']
        )->assertStatus(200);

        $this->assertDatabaseHas('attendance_activities', [
            'employee_id' => $employee->id,
            'activity_type' => 'late_rejected',
            'actor_user_id' => $approver->user_id,
        ]);

        Carbon::setTestNow();
    }

    // ---------- 7 & 10: attendance request submission creates activity, actor = employee ----------
    public function test_attendance_request_submission_creates_activity(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $date = now()->subDay()->startOfDay();
        $employee = $this->makeEmployeeWithShift(Company::factory()->create(), $date);
        $employee->user->assignRole('employee');

        $response = $this->actingAs($employee->user)->postJson('/api/attendance-requests', [
            'attendance_date' => $date->toDateString(),
            'requested_clock_in' => $date->copy()->setTime(8, 0)->toDateTimeString(),
            'reason' => 'Lupa tap clock-in',
        ]);
        $response->assertStatus(201);

        $this->assertDatabaseHas('attendance_activities', [
            'employee_id' => $employee->id,
            'activity_type' => 'attendance_request_submitted',
            'actor_user_id' => $employee->user_id,
        ]);
    }

    // ---------- 8: attendance request decision (approve & reject) creates activity ----------
    public function test_attendance_request_approval_creates_activity(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $approver = Employee::factory()->create(['company_id' => $company->id]);
        $this->makeApprovalFlow($company, $approver, 'attendance_request');

        $date = now()->subDay()->startOfDay();
        $employee = $this->makeEmployeeWithShift($company, $date);
        $employee->user->assignRole('employee');

        $this->actingAs($employee->user)->postJson('/api/attendance-requests', [
            'attendance_date' => $date->toDateString(),
            'requested_clock_in' => $date->copy()->setTime(8, 0)->toDateTimeString(),
            'reason' => 'Lupa tap clock-in',
        ])->assertStatus(201);

        $decision = AttendanceRequestApprovalStepDecision::whereHas(
            'request',
            fn ($q) => $q->where('employee_id', $employee->id)
        )->firstOrFail();

        $this->actingAs($approver->user)->postJson(
            "/api/attendance-request-approvals/{$decision->id}/decide",
            ['action' => 'approve']
        )->assertStatus(200);

        $this->assertDatabaseHas('attendance_activities', [
            'employee_id' => $employee->id,
            'activity_type' => 'attendance_request_approved',
            'actor_user_id' => $approver->user_id,
        ]);
    }

    public function test_attendance_request_rejection_creates_activity(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $approver = Employee::factory()->create(['company_id' => $company->id]);
        $this->makeApprovalFlow($company, $approver, 'attendance_request');

        $date = now()->subDay()->startOfDay();
        $employee = $this->makeEmployeeWithShift($company, $date);
        $employee->user->assignRole('employee');

        $this->actingAs($employee->user)->postJson('/api/attendance-requests', [
            'attendance_date' => $date->toDateString(),
            'requested_clock_in' => $date->copy()->setTime(8, 0)->toDateTimeString(),
            'reason' => 'Lupa tap clock-in',
        ])->assertStatus(201);

        $decision = AttendanceRequestApprovalStepDecision::whereHas(
            'request',
            fn ($q) => $q->where('employee_id', $employee->id)
        )->firstOrFail();

        $this->actingAs($approver->user)->postJson(
            "/api/attendance-request-approvals/{$decision->id}/decide",
            ['action' => 'reject', 'notes' => 'Bukti tidak cukup']
        )->assertStatus(200);

        $this->assertDatabaseHas('attendance_activities', [
            'employee_id' => $employee->id,
            'activity_type' => 'attendance_request_rejected',
            'actor_user_id' => $approver->user_id,
        ]);
    }

    public function test_attendance_request_cancel_creates_activity(): void
    {
        $this->seed(RolePermissionSeeder::class);

        // Perlu ApprovalFlow supaya request TETAP pending setelah submit
        // (tanpa flow, initiate() auto-approve langsung -- lihat
        // AttendanceRequestApprovalService::initiate() -- dan status
        // 'approved' tidak bisa dibatalkan).
        $company = Company::factory()->create();
        $approver = Employee::factory()->create(['company_id' => $company->id]);
        $this->makeApprovalFlow($company, $approver, 'attendance_request');

        $date = now()->subDay()->startOfDay();
        $employee = $this->makeEmployeeWithShift($company, $date);
        $employee->user->assignRole('employee');

        $submit = $this->actingAs($employee->user)->postJson('/api/attendance-requests', [
            'attendance_date' => $date->toDateString(),
            'requested_clock_in' => $date->copy()->setTime(8, 0)->toDateTimeString(),
            'reason' => 'Lupa tap clock-in',
        ]);
        $submit->assertStatus(201);
        $requestId = $submit->json('data.id');

        $this->actingAs($employee->user)
            ->postJson("/api/attendance-requests/{$requestId}/cancel")
            ->assertStatus(200);

        $this->assertDatabaseHas('attendance_activities', [
            'employee_id' => $employee->id,
            'activity_type' => 'attendance_request_cancelled',
            'actor_user_id' => $employee->user_id,
        ]);
    }

    // ---------- 9: HR/Admin create & update attendance creates activity ----------
    public function test_hr_creating_attendance_creates_activity(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $admin = Employee::factory()->create(['company_id' => $company->id])->user;
        $admin->assignRole('admin');

        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($admin)->postJson('/api/attendances', [
            'employee_id' => $employee->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'present',
        ]);
        $response->assertStatus(201);

        $this->assertDatabaseHas('attendance_activities', [
            'employee_id' => $employee->id,
            'activity_type' => 'attendance_created',
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_hr_correcting_attendance_creates_activity_with_change_metadata(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $admin = Employee::factory()->create(['company_id' => $company->id])->user;
        $admin->assignRole('admin');

        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $attendance = Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'absent',
        ]);

        $response = $this->actingAs($admin)->putJson("/api/attendances/{$attendance->id}", [
            'employee_id' => $employee->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'present',
            'notes' => 'Dikoreksi HR, karyawan ternyata hadir',
        ]);
        $response->assertStatus(200);

        $activity = \App\Modules\Attendance\Models\AttendanceActivity::where('employee_id', $employee->id)
            ->where('activity_type', 'attendance_corrected')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($admin->id, $activity->actor_user_id);
        $this->assertArrayHasKey('status', $activity->metadata['changes']);
        $this->assertSame('absent', $activity->metadata['changes']['status']['old']);
        $this->assertSame('present', $activity->metadata['changes']['status']['new']);
    }

    // ---------- 12, 13, 14, 15: filter & pagination ----------
    private function seedActivitiesForFiltering(): array
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $admin = Employee::factory()->create(['company_id' => $company->id])->user;
        $admin->assignRole('admin');

        $employeeA = Employee::factory()->create(['company_id' => $company->id]);
        $employeeB = Employee::factory()->create(['company_id' => $company->id]);

        // PENTING: ambil reference waktu asli SEKALI di awal, sebelum ada
        // setTestNow apapun -- kalau now()->subDays(N) dipanggil SETELAH
        // Carbon::setTestNow() aktif, now() akan mengembalikan waktu palsu
        // yang sudah di-mock sebelumnya (numpuk), bukan waktu asli.
        $realNow = now();

        Carbon::setTestNow($realNow->copy()->subDays(5));
        $this->actingAs($admin)->postJson('/api/attendances', [
            'employee_id' => $employeeA->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'present',
        ])->assertStatus(201);

        Carbon::setTestNow($realNow->copy()->subDays(1));
        $this->actingAs($admin)->postJson('/api/attendances', [
            'employee_id' => $employeeB->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'present',
        ])->assertStatus(201);

        Carbon::setTestNow();

        return [$admin, $employeeA, $employeeB, $realNow];
    }

    public function test_activities_can_be_filtered_by_employee(): void
    {
        [$admin, $employeeA, $employeeB] = $this->seedActivitiesForFiltering();

        $response = $this->actingAs($admin)->getJson("/api/attendance-activities?employee_id={$employeeA->id}");
        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.employee_id', $employeeA->id);
    }

    public function test_activities_can_be_filtered_by_activity_type(): void
    {
        [$admin] = $this->seedActivitiesForFiltering();

        $response = $this->actingAs($admin)->getJson('/api/attendance-activities?activity_type=attendance_created');
        $response->assertOk();
        $response->assertJsonCount(2, 'data.data');
    }

    public function test_activities_can_be_filtered_by_date_range(): void
    {
        [$admin, $employeeA, , $realNow] = $this->seedActivitiesForFiltering();

        $response = $this->actingAs($admin)->getJson('/api/attendance-activities?'.http_build_query([
            'date_from' => $realNow->copy()->subDays(6)->toDateString(),
            'date_to' => $realNow->copy()->subDays(3)->toDateString(),
        ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.employee_id', $employeeA->id);
    }

    public function test_activities_are_paginated(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $admin = Employee::factory()->create(['company_id' => $company->id])->user;
        $admin->assignRole('admin');
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        for ($i = 0; $i < 25; $i++) {
            $attendance = Attendance::create([
                'employee_id' => $employee->id,
                'attendance_date' => now()->subDays($i)->toDateString(),
                'status' => 'present',
            ]);
            // Pagination test murni soal mekanik endpoint GET (bukan soal
            // "apakah event tercatat" -- itu sudah dicover test lain di atas
            // lewat actual flow), jadi di sini seed langsung ke tabel lebih
            // tepat & cepat daripada 25x panggilan HTTP clock-in/create.
            \App\Modules\Attendance\Models\AttendanceActivity::create([
                'employee_id' => $employee->id,
                'attendance_id' => $attendance->id,
                'activity_type' => 'attendance_created',
                'occurred_at' => now(),
            ]);
        }

        $response = $this->actingAs($admin)->getJson('/api/attendance-activities');
        $response->assertOk();
        $response->assertJsonPath('data.per_page', 20);
        $response->assertJsonCount(20, 'data.data');
    }

    // ---------- 16: authorization ----------
    public function test_activities_index_requires_view_attendances_permission(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        // Sengaja TIDAK assign role -> tidak punya 'view attendances'.

        $response = $this->actingAs($employee->user)->getJson('/api/attendance-activities');

        $response->assertStatus(403);
    }

    public function test_admin_with_permission_can_view_activities(): void
    {
        [$admin] = $this->seedActivitiesForFiltering();

        $response = $this->actingAs($admin)->getJson('/api/attendance-activities');

        $response->assertOk();
    }
}