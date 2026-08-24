<?php

namespace Tests\Feature\Attendance;

use App\Modules\Attendance\Models\Attendance;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\Holiday\Models\Holiday;
use App\Modules\LeaveBalance\Models\LeaveBalance;
use App\Modules\LeaveRequest\Models\LeaveRequest;
use App\Modules\LeaveType\Models\LeaveType;
use App\Modules\Shift\Models\Shift;
use App\Modules\WorkingSchedule\Models\WorkingSchedule;
use App\Modules\WorkingSchedule\Models\WorkingScheduleDetail;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * STEP B Fase 2 -- Absence Deduction workflow. Semua test lewat actual
 * endpoint HTTP, bukan panggil AbsenceDeductionService langsung.
 */
class AttendanceAbsenceDeductionTest extends TestCase
{
    use RefreshDatabase;

    private function makeEmployeeWithShift(Company $company, array $overrides = []): Employee
    {
        $employee = Employee::factory()->create(array_merge(['company_id' => $company->id], $overrides));

        $shift = Shift::create([
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
        ]);

        $workingSchedule = WorkingSchedule::create([
            'company_id' => $company->id,
            'name' => 'Default Schedule',
            'code' => 'WS-'.$employee->id,
            'is_active' => true,
        ]);

        foreach (range(1, 7) as $dayOfWeek) {
            WorkingScheduleDetail::create([
                'working_schedule_id' => $workingSchedule->id,
                'day_of_week' => $dayOfWeek,
                'shift_id' => $shift->id,
            ]);
        }

        $employee->update(['working_schedule_id' => $workingSchedule->id]);

        return $employee->fresh();
    }

    private function makeLeaveType(Company $company, array $overrides = []): LeaveType
    {
        return LeaveType::create(array_merge([
            'company_id' => $company->id,
            'name' => 'Cuti Tahunan',
            'code' => 'CT-'.$company->id.'-'.uniqid(),
            'is_paid' => true,
            'max_days_per_year' => 12,
            'min_service_months' => 0,
            'requires_attachment' => false,
            'carry_over_allowed' => false,
            'requires_approval' => true,
            'allow_half_day' => true,
            'allow_hourly' => false,
            'requires_balance' => true,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * updateOrCreate SENGAJA dipakai (bukan create) -- EmployeeLeaveBalanceObserver
     * (production, existing) auto-generate LeaveBalance setiap Employee
     * dibuat/di-update untuk semua LeaveType aktif di company itu. Kalau
     * period_start-nya kebetulan sama dengan yang manual dibikin test ini,
     * create() akan kena unique constraint. updateOrCreate aman terlepas
     * urutan/timing observer.
     */
    private function makeLeaveBalance(Employee $employee, LeaveType $leaveType, Carbon $periodStart, Carbon $periodEnd, float $initialQuota = 12, float $usedDays = 0): LeaveBalance
    {
        return LeaveBalance::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'period_start' => $periodStart->toDateString(),
            ],
            [
                'period_end' => $periodEnd->toDateString(),
                'eligible_from' => $periodStart->toDateString(),
                'initial_quota' => $initialQuota,
                'carry_over_days' => 0,
                'used_days' => $usedDays,
                'generated_at' => now(),
            ]
        );
    }

    private function makeAdmin(Company $company): \App\Models\User
    {
        $admin = Employee::factory()->create(['company_id' => $company->id])->user;
        $admin->assignRole('admin');

        return $admin;
    }

    // ---------- 1: HR/Admin can list absences ----------

    public function test_admin_can_list_absences(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $admin = $this->makeAdmin($company);
        $employee = $this->makeEmployeeWithShift($company);

        $date = Carbon::parse('2026-03-09'); // Senin, tidak ada attendance/leave

        $response = $this->actingAs($admin)->getJson('/api/attendance-report/absences?'.http_build_query([
            'date_from' => $date->toDateString(),
            'date_to' => $date->toDateString(),
        ]));

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('employee.id', $employee->id);
        $this->assertNotNull($row);
        $this->assertSame('absent', $row['status']);
        $this->assertSame($date->toDateString(), $row['date']);
    }

    // ---------- 2: Employee tidak boleh akses endpoint ----------

    public function test_employee_cannot_access_absence_endpoints(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $employee = $this->makeEmployeeWithShift($company);
        $employee->user->assignRole('employee');

        $date = Carbon::parse('2026-03-09');

        $listResponse = $this->actingAs($employee->user)->getJson('/api/attendance-report/absences?'.http_build_query([
            'date_from' => $date->toDateString(),
            'date_to' => $date->toDateString(),
        ]));
        $listResponse->assertStatus(403);

        $leaveType = $this->makeLeaveType($company);
        $markResponse = $this->actingAs($employee->user)->postJson('/api/attendance-report/absences/mark-as-time-off', [
            'employee_id' => $employee->id,
            'date' => $date->toDateString(),
            'leave_type_id' => $leaveType->id,
        ]);
        $markResponse->assertStatus(403);
    }

    // ---------- 3: absence list exclude present/holiday/leave ----------

    public function test_absence_list_excludes_present_holiday_and_approved_leave(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $admin = $this->makeAdmin($company);
        $leaveType = $this->makeLeaveType($company);

        $presentEmployee = $this->makeEmployeeWithShift($company);
        Attendance::create([
            'employee_id' => $presentEmployee->id,
            'attendance_date' => '2026-03-09',
            'status' => 'present',
        ]);

        $holidayEmployee = $this->makeEmployeeWithShift($company);
        Holiday::create([
            'company_id' => $company->id,
            'date' => '2026-03-10',
            'name' => 'Libur Test',
            'type' => 'national',
            'is_active' => true,
        ]);

        $leaveEmployee = $this->makeEmployeeWithShift($company);
        $balance = $this->makeLeaveBalance($leaveEmployee, $leaveType, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));
        LeaveRequest::create([
            'employee_id' => $leaveEmployee->id,
            'leave_type_id' => $leaveType->id,
            'leave_balance_id' => $balance->id,
            'start_date' => '2026-03-11',
            'end_date' => '2026-03-11',
            'is_half_day' => false,
            'total_days' => '1.00',
            'reason' => 'Cuti',
            'status' => 'approved',
            'requested_at' => now(),
            'decided_at' => now(),
        ]);

        $trulyAbsentEmployee = $this->makeEmployeeWithShift($company);

        $response = $this->actingAs($admin)->getJson('/api/attendance-report/absences?'.http_build_query([
            'date_from' => '2026-03-09',
            'date_to' => '2026-03-11',
        ]));

        $response->assertOk();
        $rows = collect($response->json('data'));

        $this->assertFalse($rows->contains(fn ($r) => $r['employee']['id'] === $presentEmployee->id && $r['date'] === '2026-03-09'));
        $this->assertFalse($rows->contains(fn ($r) => $r['employee']['id'] === $holidayEmployee->id && $r['date'] === '2026-03-10'));
        $this->assertFalse($rows->contains(fn ($r) => $r['employee']['id'] === $leaveEmployee->id && $r['date'] === '2026-03-11'));
        $this->assertTrue($rows->contains(fn ($r) => $r['employee']['id'] === $trulyAbsentEmployee->id));
    }

    // ---------- 4: date filter ----------

    public function test_absence_list_respects_date_filter(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $admin = $this->makeAdmin($company);
        $employee = $this->makeEmployeeWithShift($company);

        $response = $this->actingAs($admin)->getJson('/api/attendance-report/absences?'.http_build_query([
            'date_from' => '2026-03-09',
            'date_to' => '2026-03-09',
            'employee_id' => $employee->id,
        ]));

        $response->assertOk();
        $rows = collect($response->json('data'));
        $this->assertCount(1, $rows);
        $this->assertSame('2026-03-09', $rows->first()['date']);
    }

    // ---------- 5 & 6: mark as time-off happy path ----------

    public function test_admin_can_mark_absence_as_time_off_with_correct_leave_request_fields(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $admin = $this->makeAdmin($company);
        $employee = $this->makeEmployeeWithShift($company);
        $leaveType = $this->makeLeaveType($company);
        $this->makeLeaveBalance($employee, $leaveType, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));

        $date = Carbon::parse('2026-03-09');

        $response = $this->actingAs($admin)->postJson('/api/attendance-report/absences/mark-as-time-off', [
            'employee_id' => $employee->id,
            'date' => $date->toDateString(),
            'leave_type_id' => $leaveType->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.employee_id', $employee->id);
        $response->assertJsonPath('data.leave_type_id', $leaveType->id);
        $response->assertJsonPath('data.source', 'absence_deduction');
        $response->assertJsonPath('data.status', 'approved');
        $response->assertJsonPath('data.total_days', '1.00');

        // start_date/end_date dicek langsung dari DB (bukan dari JSON) --
        // Laravel 'date' cast + app timezone non-UTC bikin representasi
        // JSON-nya (ISO datetime + 'Z') kelihatan geser 1 hari walau nilai
        // yang tersimpan di kolom date Postgres-nya sendiri benar & tidak
        // ambigu. Ini kuirk serialisasi framework, bukan soal yang lagi
        // divalidasi test ini (tanggal LeaveRequest benar).
        $leaveRequest = LeaveRequest::first();
        $this->assertSame($date->toDateString(), $leaveRequest->start_date->toDateString());
        $this->assertSame($date->toDateString(), $leaveRequest->end_date->toDateString());
    }

    // ---------- 7: used_days bertambah kalau requires_balance=true ----------

    public function test_used_days_increments_when_leave_type_requires_balance(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $admin = $this->makeAdmin($company);
        $employee = $this->makeEmployeeWithShift($company);
        $leaveType = $this->makeLeaveType($company, ['requires_balance' => true]);
        $balance = $this->makeLeaveBalance($employee, $leaveType, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'), 12, 2);

        $this->actingAs($admin)->postJson('/api/attendance-report/absences/mark-as-time-off', [
            'employee_id' => $employee->id,
            'date' => '2026-03-09',
            'leave_type_id' => $leaveType->id,
        ])->assertStatus(201);

        $this->assertSame('3.00', $balance->fresh()->used_days);
    }

    // ---------- 8: used_days tidak berubah kalau requires_balance=false ----------

    public function test_used_days_untouched_when_leave_type_does_not_require_balance(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $admin = $this->makeAdmin($company);
        $employee = $this->makeEmployeeWithShift($company);
        $leaveType = $this->makeLeaveType($company, ['requires_balance' => false]);

        $response = $this->actingAs($admin)->postJson('/api/attendance-report/absences/mark-as-time-off', [
            'employee_id' => $employee->id,
            'date' => '2026-03-09',
            'leave_type_id' => $leaveType->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'approved');
        $this->assertNull($response->json('data.leave_balance_id'));
        $this->assertSame(0, LeaveBalance::count());
    }

    // ---------- 9: insufficient balance ditolak ----------

    public function test_mark_as_time_off_rejects_when_balance_insufficient(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $admin = $this->makeAdmin($company);
        $employee = $this->makeEmployeeWithShift($company);
        $leaveType = $this->makeLeaveType($company, ['requires_balance' => true]);
        $balance = $this->makeLeaveBalance($employee, $leaveType, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'), 12, 12); // saldo habis

        $response = $this->actingAs($admin)->postJson('/api/attendance-report/absences/mark-as-time-off', [
            'employee_id' => $employee->id,
            'date' => '2026-03-09',
            'leave_type_id' => $leaveType->id,
        ]);

        $response->assertStatus(422);
        $this->assertSame('12.00', $balance->fresh()->used_days);
        $this->assertSame(0, LeaveRequest::count());
    }

    // ---------- 10: duplicate/overlap ditolak ----------

    public function test_mark_as_time_off_rejects_duplicate_for_same_date(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $admin = $this->makeAdmin($company);
        $employee = $this->makeEmployeeWithShift($company);
        $leaveType = $this->makeLeaveType($company, ['requires_balance' => true]);
        $this->makeLeaveBalance($employee, $leaveType, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));

        $payload = [
            'employee_id' => $employee->id,
            'date' => '2026-03-09',
            'leave_type_id' => $leaveType->id,
        ];

        $this->actingAs($admin)->postJson('/api/attendance-report/absences/mark-as-time-off', $payload)->assertStatus(201);

        $second = $this->actingAs($admin)->postJson('/api/attendance-report/absences/mark-as-time-off', $payload);

        $second->assertStatus(422);
        $this->assertSame(1, LeaveRequest::count());
    }

    // ---------- 11 & 12: company isolation (employee vs leave type) ----------

    public function test_mark_as_time_off_rejects_leave_type_from_different_company(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $admin = $this->makeAdmin($companyA);
        $employee = $this->makeEmployeeWithShift($companyA);
        $leaveTypeFromCompanyB = $this->makeLeaveType($companyB);

        $response = $this->actingAs($admin)->postJson('/api/attendance-report/absences/mark-as-time-off', [
            'employee_id' => $employee->id,
            'date' => '2026-03-09',
            'leave_type_id' => $leaveTypeFromCompanyB->id,
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, LeaveRequest::count());
    }

    public function test_mark_as_time_off_rejects_employee_whose_company_does_not_match_leave_type(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $admin = $this->makeAdmin($companyA);
        $leaveType = $this->makeLeaveType($companyA);
        $employeeFromCompanyB = $this->makeEmployeeWithShift($companyB);

        $response = $this->actingAs($admin)->postJson('/api/attendance-report/absences/mark-as-time-off', [
            'employee_id' => $employeeFromCompanyB->id,
            'date' => '2026-03-09',
            'leave_type_id' => $leaveType->id,
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, LeaveRequest::count());
    }

    // ---------- 13: existing eligibility rules tetap berlaku ----------

    public function test_mark_as_time_off_respects_existing_eligibility_rules(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $admin = $this->makeAdmin($company);
        // Employee baru join hari ini -- belum penuhi min_service_months.
        $employee = $this->makeEmployeeWithShift($company, ['join_date' => now()->toDateString()]);
        $leaveType = $this->makeLeaveType($company, ['min_service_months' => 12]);

        $response = $this->actingAs($admin)->postJson('/api/attendance-report/absences/mark-as-time-off', [
            'employee_id' => $employee->id,
            'date' => '2026-03-09',
            'leave_type_id' => $leaveType->id,
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, LeaveRequest::count());
    }

    // ---------- 14: transaction rollback kalau balance lookup gagal di tengah ----------

    public function test_no_orphan_leave_request_created_when_balance_lookup_fails(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $admin = $this->makeAdmin($company);
        $employee = $this->makeEmployeeWithShift($company);
        // requires_balance=true TAPI sengaja tidak bikin LeaveBalance sama sekali.
        $leaveType = $this->makeLeaveType($company, ['requires_balance' => true]);

        $response = $this->actingAs($admin)->postJson('/api/attendance-report/absences/mark-as-time-off', [
            'employee_id' => $employee->id,
            'date' => '2026-03-09',
            'leave_type_id' => $leaveType->id,
        ]);

        $response->assertStatus(422);
        // Guard balance gagal DI DALAM transaction, sebelum LeaveRequest::create()
        // -- pastikan tidak ada row nyangkut / partial state.
        $this->assertSame(0, LeaveRequest::count());
    }

    // ---------- 15 & 16: report & daily recap konsisten setelah deduction ----------

    public function test_report_and_daily_recap_reflect_leave_after_deduction(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $admin = $this->makeAdmin($company);
        $employee = $this->makeEmployeeWithShift($company);
        $leaveType = $this->makeLeaveType($company, ['requires_balance' => true]);
        $this->makeLeaveBalance($employee, $leaveType, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));

        $date = '2026-03-09';

        $this->actingAs($admin)->postJson('/api/attendance-report/absences/mark-as-time-off', [
            'employee_id' => $employee->id,
            'date' => $date,
            'leave_type_id' => $leaveType->id,
        ])->assertStatus(201);

        // Absence list poin 15: tanggal itu gak lagi muncul sebagai absent.
        $absenceList = $this->actingAs($admin)->getJson('/api/attendance-report/absences?'.http_build_query([
            'date_from' => $date, 'date_to' => $date,
        ]));
        $absenceList->assertOk();
        $this->assertFalse(
            collect($absenceList->json('data'))->contains(fn ($r) => $r['employee']['id'] === $employee->id)
        );

        // Daily recap poin 16: status 'leave'.
        $daily = $this->actingAs($admin)->getJson("/api/attendance-report/employees/{$employee->id}/daily?".http_build_query([
            'date_from' => $date, 'date_to' => $date,
        ]));
        $daily->assertOk();
        $daily->assertJsonPath('data.0.status', 'leave');
    }

    // ---------- 17: requires_approval=true tetap langsung approved ----------

    public function test_mark_as_time_off_bypasses_requires_approval_flag(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $admin = $this->makeAdmin($company);
        $employee = $this->makeEmployeeWithShift($company);
        $leaveType = $this->makeLeaveType($company, ['requires_approval' => true, 'requires_balance' => false]);

        $response = $this->actingAs($admin)->postJson('/api/attendance-report/absences/mark-as-time-off', [
            'employee_id' => $employee->id,
            'date' => '2026-03-09',
            'leave_type_id' => $leaveType->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'approved');
    }

    // ---------- 18: existing LeaveRequest submission tidak berubah ----------

    public function test_normal_leave_submission_still_defaults_to_self_submitted_source(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $employee = $this->makeEmployeeWithShift($company);
        $employee->user->assignRole('employee');
        $leaveType = $this->makeLeaveType($company, ['requires_balance' => false, 'requires_approval' => false]);

        $response = $this->actingAs($employee->user)->postJson('/api/leave-requests', [
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-03-09',
            'end_date' => '2026-03-09',
            'reason' => 'Cuti biasa',
        ]);

        $response->assertStatus(201);
        $this->assertSame('self_submitted', LeaveRequest::first()->source->value);
    }
}