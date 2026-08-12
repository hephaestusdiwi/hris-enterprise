<?php

namespace Tests\Feature\AttendanceRequest;

use App\Modules\ApprovalFlow\Enums\ApproverType;
use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\ApprovalFlow\Models\ApprovalStep;
use App\Modules\Attendance\Enums\AttendanceApprovalRequestType;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Attendance\Models\AttendanceApprovalRequest;
use App\Modules\Attendance\Services\AttendanceApprovalService;
use App\Modules\AttendanceRequest\Models\AttendanceRequest;
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

class AttendanceRequestApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

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
     * Approval flow company-wide 1 step, approver = specific employee.
     * Persis pola yang dipakai EmployeeMovementFlowTest.
     */
    private function makeApprovalFlow(Company $company, Employee $approver): ApprovalFlow
    {
        $flow = ApprovalFlow::create([
            'company_id' => $company->id,
            'name' => 'Default Flow',
            'code' => 'DEFAULT-'.$company->id,
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

    public function test_approval_creates_new_attendance_and_recalculates_late_status(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $date = now()->subDay()->startOfDay();

        $employee = $this->makeEmployeeWithShift($company, $date);
        $approver = Employee::factory()->create(['company_id' => $company->id]);
        $this->makeApprovalFlow($company, $approver);

        $employee->user->assignRole('employee');

        $submit = $this->actingAs($employee->user)->postJson('/api/attendance-requests', [
            'attendance_date' => $date->toDateString(),
            'requested_clock_in' => $date->copy()->setTime(8, 30)->toDateTimeString(), // 30 menit dari shift start 08:00
            'reason' => 'Sistem down, baru bisa lapor manual',
        ]);
        $submit->assertStatus(201);
        $this->assertSame('pending', $submit->json('data.status')); // ada approval flow -> harus pending, bukan auto-approve

        $decisionId = AttendanceRequestApprovalStepDecision::first()->id;

        $decide = $this->actingAs($approver->user)->postJson(
            "/api/attendance-request-approvals/{$decisionId}/decide",
            ['action' => 'approve']
        );
        $decide->assertOk();

        $attendanceRequest = AttendanceRequest::first();
        $this->assertSame('approved', $attendanceRequest->status->value);
        $this->assertNotNull($attendanceRequest->attendance_id);

        $attendance = Attendance::find($attendanceRequest->attendance_id);
        $this->assertNotNull($attendance);
        $this->assertSame('08:30:00', $attendance->clock_in->format('H:i:s'));
        $this->assertSame(30, $attendance->late_minutes); // tolerance 10 menit, telat 30 menit dari shift start 08:00
        $this->assertFalse($attendance->within_grace);
        $this->assertSame('late', $attendance->status->value);
        $this->assertSame('attendance_request', $attendance->clock_in_method);
    }

    public function test_approval_updates_existing_attendance_and_recalculates_overtime(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $date = now()->subDay()->startOfDay();

        $employee = $this->makeEmployeeWithShift($company, $date, ['overtime_threshold_minutes' => 30]);
        $approver = Employee::factory()->create(['company_id' => $company->id]);
        $this->makeApprovalFlow($company, $approver);
        $employee->user->assignRole('employee');

        $shift = Shift::where('company_id', $company->id)->firstOrFail();

        $attendance = Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => $date->toDateString(),
            'shift_id' => $shift->id,
            'clock_in' => $date->copy()->setTime(8, 0),
            'late_minutes' => 0,
            'within_grace' => true,
            'status' => 'present',
        ]);

        $submit = $this->actingAs($employee->user)->postJson('/api/attendance-requests', [
            'attendance_date' => $date->toDateString(),
            // Shift end 17:00, overtime threshold 30 menit -> clock-out 18:00 = 60 menit overtime, di atas threshold.
            'requested_clock_out' => $date->copy()->setTime(18, 0)->toDateTimeString(),
            'reason' => 'Lupa tap clock-out kemarin, lembur sampai jam 6',
        ]);
        $submit->assertStatus(201);

        $decisionId = AttendanceRequestApprovalStepDecision::first()->id;
        $this->actingAs($approver->user)
            ->postJson("/api/attendance-request-approvals/{$decisionId}/decide", ['action' => 'approve'])
            ->assertOk();

        $attendanceRequest = AttendanceRequest::first();
        // Attendance ID tidak berubah -- ini UPDATE row existing, bukan create baru.
        $this->assertSame($attendance->id, $attendanceRequest->attendance_id);

        $attendance = $attendance->fresh();
        $this->assertSame('18:00:00', $attendance->clock_out->format('H:i:s'));
        $this->assertSame(60, $attendance->detected_overtime_minutes);
        // Clock-in dari clock-in normal sebelumnya tidak ikut berubah.
        $this->assertSame('08:00:00', $attendance->clock_in->format('H:i:s'));
    }

    public function test_rejection_leaves_attendance_untouched(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $date = now()->subDay()->startOfDay();

        $employee = $this->makeEmployeeWithShift($company, $date);
        $approver = Employee::factory()->create(['company_id' => $company->id]);
        $this->makeApprovalFlow($company, $approver);
        $employee->user->assignRole('employee');

        $shift = Shift::where('company_id', $company->id)->firstOrFail();

        $attendance = Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => $date->toDateString(),
            'shift_id' => $shift->id,
            'clock_in' => $date->copy()->setTime(8, 0),
            'late_minutes' => 0,
            'within_grace' => true,
            'status' => 'present',
        ]);

        $this->actingAs($employee->user)->postJson('/api/attendance-requests', [
            'attendance_date' => $date->toDateString(),
            'requested_clock_in' => $date->copy()->setTime(9, 0)->toDateTimeString(),
            'reason' => 'Koreksi clock-in (ternyata ditolak approver)',
        ])->assertStatus(201);

        $decisionId = AttendanceRequestApprovalStepDecision::first()->id;
        $this->actingAs($approver->user)
            ->postJson("/api/attendance-request-approvals/{$decisionId}/decide", [
                'action' => 'reject',
                'notes' => 'Tidak sesuai laporan device',
            ])
            ->assertOk();

        $this->assertSame('rejected', AttendanceRequest::first()->status->value);

        $attendance = $attendance->fresh();
        // Attendance SAMA SEKALI tidak berubah.
        $this->assertSame('08:00:00', $attendance->clock_in->format('H:i:s'));
    }

    public function test_cancellation_only_allowed_while_pending(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $date = now()->subDay()->startOfDay();

        $employee = $this->makeEmployeeWithShift($company, $date);
        $approver = Employee::factory()->create(['company_id' => $company->id]);
        $this->makeApprovalFlow($company, $approver);
        $employee->user->assignRole('employee');

        $submit = $this->actingAs($employee->user)->postJson('/api/attendance-requests', [
            'attendance_date' => $date->toDateString(),
            'requested_clock_in' => $date->copy()->setTime(8, 0)->toDateTimeString(),
            'reason' => 'Mau dibatalkan lagi',
        ]);
        $attendanceRequestId = $submit->json('data.id');

        $cancel = $this->actingAs($employee->user)->postJson("/api/attendance-requests/{$attendanceRequestId}/cancel");
        $cancel->assertOk();
        $this->assertSame('cancelled', AttendanceRequest::find($attendanceRequestId)->status->value);

        // Cancel kedua kali -> ditolak, karena sudah tidak pending.
        $secondCancel = $this->actingAs($employee->user)->postJson("/api/attendance-requests/{$attendanceRequestId}/cancel");
        $secondCancel->assertStatus(422);
    }

    public function test_employee_cannot_cancel_other_employees_request(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $date = now()->subDay()->startOfDay();

        $employee = $this->makeEmployeeWithShift($company, $date);
        $approver = Employee::factory()->create(['company_id' => $company->id]);
        $this->makeApprovalFlow($company, $approver);
        $employee->user->assignRole('employee');

        $otherEmployee = Employee::factory()->create(['company_id' => $company->id]);
        $otherEmployee->user->assignRole('employee');

        $submit = $this->actingAs($employee->user)->postJson('/api/attendance-requests', [
            'attendance_date' => $date->toDateString(),
            'requested_clock_in' => $date->copy()->setTime(8, 0)->toDateTimeString(),
            'reason' => 'Punya si A',
        ]);
        $attendanceRequestId = $submit->json('data.id');

        $response = $this->actingAs($otherEmployee->user)->postJson("/api/attendance-requests/{$attendanceRequestId}/cancel");

        $response->assertStatus(403);
        $this->assertSame('pending', AttendanceRequest::find($attendanceRequestId)->status->value);
    }

    public function test_approver_outside_scope_cannot_decide(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $date = now()->subDay()->startOfDay();

        $employee = $this->makeEmployeeWithShift($company, $date);
        $approver = Employee::factory()->create(['company_id' => $company->id]);
        $this->makeApprovalFlow($company, $approver);
        $employee->user->assignRole('employee');

        $randomOutsider = Employee::factory()->create(['company_id' => $company->id]);

        $this->actingAs($employee->user)->postJson('/api/attendance-requests', [
            'attendance_date' => $date->toDateString(),
            'requested_clock_in' => $date->copy()->setTime(8, 0)->toDateTimeString(),
            'reason' => 'Test scope approver',
        ])->assertStatus(201);

        $decisionId = AttendanceRequestApprovalStepDecision::first()->id;

        $response = $this->actingAs($randomOutsider->user)->postJson(
            "/api/attendance-request-approvals/{$decisionId}/decide",
            ['action' => 'approve']
        );

        $response->assertStatus(422); // AttendanceRequestApprovalException: "Anda tidak berwenang..."
        $this->assertSame('pending', AttendanceRequest::first()->status->value);
    }

    /**
     * Regresi bagian 1: AttendanceRequest yang approved dan menghasilkan
     * status Late TIDAK memicu AttendanceApprovalRequest (mekanisme Late/OT
     * yang sudah ada) sama sekali -- sesuai keputusan "final approval".
     */
    public function test_approved_attendance_request_does_not_create_late_overtime_approval(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $date = now()->subDay()->startOfDay();

        $employee = $this->makeEmployeeWithShift($company, $date);
        $approver = Employee::factory()->create(['company_id' => $company->id]);
        $this->makeApprovalFlow($company, $approver);
        $employee->user->assignRole('employee');

        $this->actingAs($employee->user)->postJson('/api/attendance-requests', [
            'attendance_date' => $date->toDateString(),
            'requested_clock_in' => $date->copy()->setTime(9, 0)->toDateTimeString(), // jelas telat
            'reason' => 'Telat parah, sistem juga sempat down',
        ])->assertStatus(201);

        $decisionId = AttendanceRequestApprovalStepDecision::first()->id;
        $this->actingAs($approver->user)
            ->postJson("/api/attendance-request-approvals/{$decisionId}/decide", ['action' => 'approve'])
            ->assertOk();

        $this->assertSame('late', Attendance::first()->status->value);
        // Tabel Late/OT approval (existing, tidak boleh disentuh) harus TETAP KOSONG.
        $this->assertDatabaseCount('attendance_approval_requests', 0);
    }

    /**
     * Regresi bagian 2: mekanisme Late/OT approval yang sudah ada (dipicu
     * dari AttendanceService::doClockIn/doClockOut) tetap berjalan normal,
     * memakai ApprovalFlow generic yang sama, sekalipun module
     * AttendanceRequest sekarang juga eksis dan memakai flow yang sama.
     *
     * Sengaja dites di level Service (bukan lewat endpoint HTTP
     * /api/attendance/clock-in) supaya tidak bercampur dengan precondition
     * GPS/foto self-service yang tidak relevan dengan yang mau dibuktikan
     * di sini: bahwa AttendanceApprovalService (Late/OT) berjalan tidak
     * berubah dan tidak terganggu oleh keberadaan AttendanceRequestApprovalService.
     */
    public function test_existing_late_overtime_approval_mechanism_still_works_unaffected(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $date = now()->subDay()->startOfDay();

        $employee = $this->makeEmployeeWithShift($company, $date);
        $approver = Employee::factory()->create(['company_id' => $company->id]);
        $this->makeApprovalFlow($company, $approver);

        $shift = Shift::where('company_id', $company->id)->firstOrFail();

        $attendance = Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => $date->toDateString(),
            'shift_id' => $shift->id,
            'clock_in' => $date->copy()->setTime(8, 45),
            'late_minutes' => 45,
            'within_grace' => false,
            'status' => 'late',
        ]);

        app(AttendanceApprovalService::class)->handleLateDetected($attendance, 45);

        $this->assertDatabaseCount('attendance_approval_requests', 1);
        $this->assertDatabaseHas('attendance_approval_requests', [
            'attendance_id' => $attendance->id,
            'type' => AttendanceApprovalRequestType::Late->value,
            'status' => 'pending',
            'detected_value' => 45,
        ]);

        // Sekaligus buktikan tabel AttendanceRequest (module baru) tidak
        // ikut kesenggol oleh alur Late/OT ini.
        $this->assertDatabaseCount('attendance_requests', 0);
    }
}
