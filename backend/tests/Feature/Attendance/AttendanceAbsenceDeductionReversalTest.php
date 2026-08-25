<?php

namespace Tests\Feature\Attendance;

use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
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
 * STEP B Fase 2b -- Absence Deduction Reversal ("Correction/Clear" ala
 * Talenta). Semua test lewat actual endpoint HTTP. Reversal HANYA berlaku
 * untuk source=absence_deduction, TIDAK PERNAH generic Leave cancellation
 * (LeaveRequestService::cancel() sama sekali tidak disentuh implementasi
 * ini -- lihat test #15 regression guard).
 */
class AttendanceAbsenceDeductionReversalTest extends TestCase
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

    private function makeLeaveBalance(Employee $employee, LeaveType $leaveType, Carbon $periodStart, Carbon $periodEnd, float $initialQuota = 12, float $usedDays = 0): LeaveBalance
    {
        // updateOrCreate SENGAJA dipakai -- EmployeeLeaveBalanceObserver
        // (existing) auto-generate LeaveBalance tiap Employee dibuat/update.
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

    /**
     * Helper: bikin absence deduction beneran lewat endpoint (bukan
     * langsung insert LeaveRequest), biar konsisten "actual application
     * flow" -- mengembalikan [admin, employee, leaveType, balance, leaveRequestId].
     */
    private function markAsTimeOff(Company $company, string $date = '2026-03-09', array $leaveTypeOverrides = []): array
    {
        $admin = $this->makeAdmin($company);
        $employee = $this->makeEmployeeWithShift($company);
        $leaveType = $this->makeLeaveType($company, $leaveTypeOverrides);
        $balance = null;

        if ($leaveType->requires_balance) {
            $balance = $this->makeLeaveBalance($employee, $leaveType, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));
        }

        $response = $this->actingAs($admin)->postJson('/api/attendance-report/absences/mark-as-time-off', [
            'employee_id' => $employee->id,
            'date' => $date,
            'leave_type_id' => $leaveType->id,
        ]);
        $response->assertStatus(201);

        return [$admin, $employee, $leaveType, $balance, $response->json('data.id')];
    }

    // ---------- 1: basic reversal ----------

    public function test_basic_reversal_changes_status_to_reversed(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        [$admin, , , , $leaveRequestId] = $this->markAsTimeOff($company);

        $this->assertSame('approved', LeaveRequest::find($leaveRequestId)->status->value);

        $response = $this->actingAs($admin)->postJson("/api/attendance-report/absences/{$leaveRequestId}/reverse");

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'reversed');
        $this->assertSame('reversed', LeaveRequest::find($leaveRequestId)->status->value);
    }

    // ---------- 2: balance restored ----------

    public function test_balance_restored_to_original_value_after_reversal(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        $admin = $this->makeAdmin($company);
        $employee = $this->makeEmployeeWithShift($company);
        $leaveType = $this->makeLeaveType($company, ['requires_balance' => true]);
        $balance = $this->makeLeaveBalance($employee, $leaveType, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'), 12, 3);

        $mark = $this->actingAs($admin)->postJson('/api/attendance-report/absences/mark-as-time-off', [
            'employee_id' => $employee->id,
            'date' => '2026-03-09',
            'leave_type_id' => $leaveType->id,
        ]);
        $mark->assertStatus(201);
        $this->assertSame('4.00', $balance->fresh()->used_days); // 3 + 1

        $this->actingAs($admin)->postJson("/api/attendance-report/absences/{$mark->json('data.id')}/reverse")->assertStatus(200);

        $this->assertSame('3.00', $balance->fresh()->used_days); // balik ke semula
    }

    // ---------- 3: no balance (requires_balance=false) ----------

    public function test_reversal_succeeds_without_touching_balance_when_leave_balance_id_is_null(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        [$admin, , , , $leaveRequestId] = $this->markAsTimeOff($company, '2026-03-09', ['requires_balance' => false]);

        $this->assertNull(LeaveRequest::find($leaveRequestId)->leave_balance_id);

        $response = $this->actingAs($admin)->postJson("/api/attendance-report/absences/{$leaveRequestId}/reverse");

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'reversed');
        $this->assertSame(0, LeaveBalance::count()); // nol balance pernah dibuat sama sekali
    }

    // ---------- 4: wrong source ----------

    public function test_self_submitted_leave_request_cannot_be_reversed(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        $admin = $this->makeAdmin($company);
        $employee = $this->makeEmployeeWithShift($company);
        $leaveType = $this->makeLeaveType($company, ['requires_balance' => false, 'requires_approval' => false]);

        $employee->user->assignRole('employee');
        $submit = $this->actingAs($employee->user)->postJson('/api/leave-requests', [
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-03-09',
            'end_date' => '2026-03-09',
            'reason' => 'Cuti biasa',
        ]);
        $submit->assertStatus(201);
        $this->assertSame('self_submitted', LeaveRequest::first()->source->value);

        $response = $this->actingAs($admin)->postJson("/api/attendance-report/absences/{$submit->json('data.id')}/reverse");

        $response->assertStatus(422);
        $this->assertSame('approved', LeaveRequest::first()->status->value); // gak ke-ubah sama sekali
    }

    // ---------- 5: wrong status ----------

    public function test_pending_leave_request_cannot_be_reversed(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        $admin = $this->makeAdmin($company);
        $employee = $this->makeEmployeeWithShift($company);
        $leaveType = $this->makeLeaveType($company, ['requires_balance' => false]);

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-03-09',
            'end_date' => '2026-03-09',
            'is_half_day' => false,
            'total_days' => '1.00',
            'reason' => 'Test',
            'status' => 'pending',
            'source' => 'absence_deduction',
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($admin)->postJson("/api/attendance-report/absences/{$leaveRequest->id}/reverse");

        $response->assertStatus(422);
    }

    public function test_rejected_leave_request_cannot_be_reversed(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        $admin = $this->makeAdmin($company);
        $employee = $this->makeEmployeeWithShift($company);
        $leaveType = $this->makeLeaveType($company, ['requires_balance' => false]);

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-03-09',
            'end_date' => '2026-03-09',
            'is_half_day' => false,
            'total_days' => '1.00',
            'reason' => 'Test',
            'status' => 'rejected',
            'source' => 'absence_deduction',
            'requested_at' => now(),
            'decided_at' => now(),
        ]);

        $response = $this->actingAs($admin)->postJson("/api/attendance-report/absences/{$leaveRequest->id}/reverse");

        $response->assertStatus(422);
    }

    public function test_already_reversed_leave_request_cannot_be_reversed_again_explicit_status(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        $admin = $this->makeAdmin($company);
        $employee = $this->makeEmployeeWithShift($company);
        $leaveType = $this->makeLeaveType($company, ['requires_balance' => false]);

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-03-09',
            'end_date' => '2026-03-09',
            'is_half_day' => false,
            'total_days' => '1.00',
            'reason' => 'Test',
            'status' => 'reversed',
            'source' => 'absence_deduction',
            'requested_at' => now(),
            'decided_at' => now(),
            'reversed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->postJson("/api/attendance-report/absences/{$leaveRequest->id}/reverse");

        $response->assertStatus(422);
    }

    // ---------- 6: double reversal (juga membuktikan guard konkuren -- lihat #12) ----------

    public function test_double_reversal_is_rejected_and_used_days_only_changes_once(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        $admin = $this->makeAdmin($company);
        $employee = $this->makeEmployeeWithShift($company);
        $leaveType = $this->makeLeaveType($company, ['requires_balance' => true]);
        $balance = $this->makeLeaveBalance($employee, $leaveType, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'), 12, 0);

        $mark = $this->actingAs($admin)->postJson('/api/attendance-report/absences/mark-as-time-off', [
            'employee_id' => $employee->id,
            'date' => '2026-03-09',
            'leave_type_id' => $leaveType->id,
        ]);
        $mark->assertStatus(201);
        $leaveRequestId = $mark->json('data.id');

        $first = $this->actingAs($admin)->postJson("/api/attendance-report/absences/{$leaveRequestId}/reverse");
        $first->assertStatus(200);
        $this->assertSame('0.00', $balance->fresh()->used_days);

        $second = $this->actingAs($admin)->postJson("/api/attendance-report/absences/{$leaveRequestId}/reverse");
        $second->assertStatus(422);

        // used_days TIDAK boleh berubah lagi (gak boleh jadi -1.00).
        $this->assertSame('0.00', $balance->fresh()->used_days);
    }

    // ---------- 7: permission ----------

    public function test_employee_cannot_reverse_absence_deduction(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        [, , , , $leaveRequestId] = $this->markAsTimeOff($company);

        $otherEmployee = $this->makeEmployeeWithShift($company);
        $otherEmployee->user->assignRole('employee');

        $response = $this->actingAs($otherEmployee->user)->postJson("/api/attendance-report/absences/{$leaveRequestId}/reverse");

        $response->assertStatus(403);
        $this->assertSame('approved', LeaveRequest::find($leaveRequestId)->status->value);
    }

    public function test_admin_with_manage_absence_deductions_permission_can_reverse(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        [$admin, , , , $leaveRequestId] = $this->markAsTimeOff($company);

        $response = $this->actingAs($admin)->postJson("/api/attendance-report/absences/{$leaveRequestId}/reverse");

        $response->assertStatus(200);
    }

    // ---------- 8: absence recalculation ----------

    public function test_date_returns_to_absent_after_reversal(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        [$admin, $employee, , , $leaveRequestId] = $this->markAsTimeOff($company, '2026-03-09');

        // Sebelum reverse: bukan absent lagi (udah leave).
        $before = $this->actingAs($admin)->getJson('/api/attendance-report/absences?'.http_build_query([
            'date_from' => '2026-03-09', 'date_to' => '2026-03-09',
        ]));
        $this->assertFalse(collect($before->json('data'))->contains(fn ($r) => $r['employee']['id'] === $employee->id));

        $this->actingAs($admin)->postJson("/api/attendance-report/absences/{$leaveRequestId}/reverse")->assertStatus(200);

        // Setelah reverse: absent lagi, TANPA ada Attendance row baru.
        $after = $this->actingAs($admin)->getJson('/api/attendance-report/absences?'.http_build_query([
            'date_from' => '2026-03-09', 'date_to' => '2026-03-09',
        ]));
        $this->assertTrue(collect($after->json('data'))->contains(fn ($r) => $r['employee']['id'] === $employee->id));

        $daily = $this->actingAs($admin)->getJson("/api/attendance-report/employees/{$employee->id}/daily?".http_build_query([
            'date_from' => '2026-03-09', 'date_to' => '2026-03-09',
        ]));
        $daily->assertJsonPath('data.0.status', 'absent');

        $this->assertSame(0, \App\Modules\Attendance\Models\Attendance::count());
    }

    // ---------- 9: history preservation ----------

    public function test_leave_request_row_and_source_preserved_after_reversal(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        [$admin, , , , $leaveRequestId] = $this->markAsTimeOff($company);

        $this->actingAs($admin)->postJson(
            "/api/attendance-report/absences/{$leaveRequestId}/reverse",
            ['reason' => 'Salah tanggal']
        )->assertStatus(200);

        $leaveRequest = LeaveRequest::find($leaveRequestId);
        $this->assertNotNull($leaveRequest); // row TIDAK dihapus
        $this->assertSame('absence_deduction', $leaveRequest->source->value); // source tetap
        $this->assertSame('reversed', $leaveRequest->status->value);
        $this->assertSame($admin->id, $leaveRequest->reversed_by_user_id);
        $this->assertNotNull($leaveRequest->reversed_at);
        $this->assertSame('Salah tanggal', $leaveRequest->reversal_reason);
    }

    // ---------- 10: total_days aktual (bukan hardcode) ----------

    public function test_reversal_uses_actual_total_days_not_hardcoded(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        $employee = $this->makeEmployeeWithShift($company);
        $leaveType = $this->makeLeaveType($company, ['requires_balance' => true]);
        $balance = $this->makeLeaveBalance($employee, $leaveType, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'), 12, 2);
        $admin = $this->makeAdmin($company);

        // total_days != 1.00 -- absence deduction sekarang selalu 1.00, jadi
        // di sini gua insert LeaveRequest absence_deduction MANUAL dengan
        // total_days custom (0.50) buat buktiin reversal-nya generic
        // (pakai kolom total_days aktual), bukan hardcode angka 1.
        $balance->update(['used_days' => '2.50']);
        $leaveRequest = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'leave_balance_id' => $balance->id,
            'start_date' => '2026-03-09',
            'end_date' => '2026-03-09',
            'is_half_day' => true,
            'total_days' => '0.50',
            'reason' => 'Test half day',
            'status' => 'approved',
            'source' => 'absence_deduction',
            'requested_at' => now(),
            'decided_at' => now(),
        ]);

        $this->actingAs($admin)->postJson("/api/attendance-report/absences/{$leaveRequest->id}/reverse")->assertStatus(200);

        $this->assertSame('2.00', $balance->fresh()->used_days); // 2.50 - 0.50, BUKAN 2.50-1.00=1.50
    }

    // ---------- 11: company isolation ----------

    public function test_reversal_of_leave_request_from_different_company_is_still_source_and_status_gated(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        [, , , , $leaveRequestIdCompanyA] = $this->markAsTimeOff($companyA);
        $adminCompanyB = $this->makeAdmin($companyB);

        // Konsisten sama precedent Phase 2 (admin/hr full cross-company
        // visibility by design di seluruh sistem ini -- EmployeePolicy) --
        // guard yang menegakkan reversal adalah source+status, BUKAN
        // company milik actor (bukan konsep yang applicable di sistem ini).
        // Test ini membuktikan endpoint tetap KONSISTEN, bukan bahwa admin
        // company lain di-403.
        $response = $this->actingAs($adminCompanyB)->postJson("/api/attendance-report/absences/{$leaveRequestIdCompanyA}/reverse");

        $response->assertStatus(200); // reversal tetap valid: source=absence_deduction, status=approved
    }

    // ---------- 12: concurrent reversal (dibuktikan lewat guard sequential -- lihat catatan di atas test #6) ----------

    public function test_concurrent_style_reversal_only_decrements_once(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        $admin = $this->makeAdmin($company);
        $employee = $this->makeEmployeeWithShift($company);
        $leaveType = $this->makeLeaveType($company, ['requires_balance' => true]);
        $balance = $this->makeLeaveBalance($employee, $leaveType, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'), 12, 5);

        $mark = $this->actingAs($admin)->postJson('/api/attendance-report/absences/mark-as-time-off', [
            'employee_id' => $employee->id,
            'date' => '2026-03-09',
            'leave_type_id' => $leaveType->id,
        ]);
        $leaveRequestId = $mark->json('data.id');
        $this->assertSame('6.00', $balance->fresh()->used_days);

        // Simulasi 2 request reversal "bersamaan" -- lockForUpdate() di
        // dalam DB::transaction() menjamin yang kedua akan nemuin row
        // sudah ter-update (status != approved) begitu lock request
        // pertama dilepas, BUKAN baca stale state.
        $results = [
            $this->actingAs($admin)->postJson("/api/attendance-report/absences/{$leaveRequestId}/reverse")->status(),
            $this->actingAs($admin)->postJson("/api/attendance-report/absences/{$leaveRequestId}/reverse")->status(),
        ];

        $this->assertEqualsCanonicalizing([200, 422], $results);
        $this->assertSame('5.00', $balance->fresh()->used_days); // cuma berkurang SATU kali
    }

    // ---------- 13/15: regression -- normal LeaveRequest cancellation tidak berubah ----------

    public function test_normal_pending_leave_cancellation_behavior_unchanged(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        $employee = $this->makeEmployeeWithShift($company, ['company_id' => $company->id]);
        $employee->user->assignRole('employee');

        $approver = Employee::factory()->create(['company_id' => $company->id]);
        $leaveType = $this->makeLeaveType($company, ['requires_balance' => false, 'requires_approval' => true]);

        \App\Modules\ApprovalFlow\Models\ApprovalFlow::create([
            'company_id' => $company->id,
            'name' => 'Default Flow',
            'code' => 'DEFAULT-LEAVE-'.$company->id,
            'approval_type' => 'leave',
            'is_active' => true,
        ])->steps()->create([
            'sequence' => 1,
            'approver_type' => \App\Modules\ApprovalFlow\Enums\ApproverType::SpecificEmployee->value,
            'approver_employee_id' => $approver->id,
            'is_active' => true,
        ]);

        $submit = $this->actingAs($employee->user)->postJson('/api/leave-requests', [
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-03-09',
            'end_date' => '2026-03-09',
            'reason' => 'Cuti biasa',
        ]);
        $submit->assertStatus(201);
        $submit->assertJsonPath('data.status', 'pending'); // masih pending, LeaveRequestService::cancel() TIDAK gua sentuh

        $cancel = $this->actingAs($employee->user)->postJson("/api/leave-requests/{$submit->json('data.id')}/cancel");
        $cancel->assertStatus(200);
        $this->assertSame('cancelled', LeaveRequest::find($submit->json('data.id'))->status->value);
    }
}