<?php

namespace Tests\Feature\Attendance;

use App\Modules\Attendance\Contracts\LeaveCheckerInterface;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\Holiday\Models\Holiday;
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
 * STEP B Fase 1 -- Absence Deduction (deteksi, bukan LeaveBalance deduction).
 *
 * Fokus: DatabaseLeaveChecker (implementasi asli LeaveCheckerInterface,
 * menggantikan NullLeaveChecker) dan AttendanceReportService yang sekarang
 * leave-aware. Tidak ada tabel/model/endpoint baru -- semua tetap derived.
 */
class AttendanceAbsenceReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Working schedule berlaku SEMUA hari (Senin-Minggu) supaya setiap
     * tanggal di rentang test predictable sebagai "hari kerja" tanpa perlu
     * peduli hari apa test kebetulan jalan.
     */
    private function makeEmployeeWithShift(Company $company): Employee
    {
        $employee = Employee::factory()->create(['company_id' => $company->id]);

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

    private function makeLeaveType(Company $company): LeaveType
    {
        return LeaveType::create([
            'company_id' => $company->id,
            'name' => 'Cuti Tahunan',
            'code' => 'CT-'.$company->id,
            'is_paid' => true,
            'min_service_months' => 0,
            'requires_attachment' => false,
            'carry_over_allowed' => false,
            'requires_approval' => true,
            'allow_half_day' => true,
            'allow_hourly' => false,
            'requires_balance' => false,
            'is_active' => true,
        ]);
    }

    private function makeLeaveRequest(
        Employee $employee,
        LeaveType $leaveType,
        Carbon $start,
        Carbon $end,
        string $status,
        bool $isHalfDay = false,
    ): LeaveRequest {
        return LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'is_half_day' => $isHalfDay,
            'total_days' => $start->diffInDays($end) + 1,
            'reason' => 'Test leave',
            'status' => $status,
            'requested_at' => now(),
            'decided_at' => in_array($status, ['approved', 'rejected'], true) ? now() : null,
        ]);
    }

    // ---------- 1-5: DatabaseLeaveChecker (resolve via container -- turut memvalidasi binding baru) ----------

    public function test_leave_checker_returns_true_for_approved_leave_on_date_within_range(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $leaveType = $this->makeLeaveType($company);

        $start = Carbon::parse('2026-03-10');
        $end = Carbon::parse('2026-03-12');
        $this->makeLeaveRequest($employee, $leaveType, $start, $end, 'approved');

        $checker = app(LeaveCheckerInterface::class);

        $this->assertTrue($checker->isOnLeave($employee->id, Carbon::parse('2026-03-11')));
    }

    public function test_leave_checker_returns_false_for_pending_leave(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $leaveType = $this->makeLeaveType($company);

        $date = Carbon::parse('2026-03-11');
        $this->makeLeaveRequest($employee, $leaveType, $date, $date, 'pending');

        $checker = app(LeaveCheckerInterface::class);

        $this->assertFalse($checker->isOnLeave($employee->id, $date));
    }

    public function test_leave_checker_returns_false_for_rejected_leave(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $leaveType = $this->makeLeaveType($company);

        $date = Carbon::parse('2026-03-11');
        $this->makeLeaveRequest($employee, $leaveType, $date, $date, 'rejected');

        $checker = app(LeaveCheckerInterface::class);

        $this->assertFalse($checker->isOnLeave($employee->id, $date));
    }

    public function test_leave_checker_returns_false_for_cancelled_leave(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $leaveType = $this->makeLeaveType($company);

        $date = Carbon::parse('2026-03-11');
        $this->makeLeaveRequest($employee, $leaveType, $date, $date, 'cancelled');

        $checker = app(LeaveCheckerInterface::class);

        $this->assertFalse($checker->isOnLeave($employee->id, $date));
    }

    public function test_leave_checker_returns_false_for_date_outside_range(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $leaveType = $this->makeLeaveType($company);

        $this->makeLeaveRequest(
            $employee,
            $leaveType,
            Carbon::parse('2026-03-10'),
            Carbon::parse('2026-03-12'),
            'approved'
        );

        $checker = app(LeaveCheckerInterface::class);

        $this->assertFalse($checker->isOnLeave($employee->id, Carbon::parse('2026-03-13')));
        $this->assertFalse($checker->isOnLeave($employee->id, Carbon::parse('2026-03-09')));
    }

    // ---------- 6, 7, 8, 9: AttendanceReportService via actual endpoint ----------

    public function test_approved_leave_on_working_day_not_counted_as_absent_and_counted_as_leave(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $admin = Employee::factory()->create(['company_id' => $company->id])->user;
        $admin->assignRole('admin');

        $employee = $this->makeEmployeeWithShift($company);
        $leaveType = $this->makeLeaveType($company);

        $dateFrom = Carbon::parse('2026-03-09'); // Senin
        $dateTo = Carbon::parse('2026-03-09');
        $this->makeLeaveRequest($employee, $leaveType, $dateFrom, $dateTo, 'approved');

        $response = $this->actingAs($admin)->getJson('/api/attendance-report?'.http_build_query([
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'employee_id' => $employee->id,
        ]));

        $response->assertOk();
        $row = collect($response->json('data.data'))->firstWhere('employee.id', $employee->id);

        $this->assertSame(1, $row['leave_days']);
        $this->assertSame(0, $row['absent_days']);
        $this->assertSame(1, $row['expected_working_days']);
    }

    public function test_working_day_without_attendance_or_leave_is_still_absent(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $admin = Employee::factory()->create(['company_id' => $company->id])->user;
        $admin->assignRole('admin');

        $employee = $this->makeEmployeeWithShift($company);

        $date = Carbon::parse('2026-03-09'); // Senin, tidak ada leave, tidak ada attendance

        $response = $this->actingAs($admin)->getJson('/api/attendance-report?'.http_build_query([
            'date_from' => $date->toDateString(),
            'date_to' => $date->toDateString(),
            'employee_id' => $employee->id,
        ]));

        $response->assertOk();
        $row = collect($response->json('data.data'))->firstWhere('employee.id', $employee->id);

        // Regression guard: behavior lama (absent kalau bener2 gak ada excuse
        // apapun) harus tetap jalan -- yang berubah cuma exclude LEAVE, bukan
        // menghilangkan absence detection itu sendiri.
        $this->assertSame(1, $row['absent_days']);
        $this->assertSame(0, $row['leave_days']);
    }

    public function test_holiday_still_not_counted_as_absent(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $admin = Employee::factory()->create(['company_id' => $company->id])->user;
        $admin->assignRole('admin');

        $employee = $this->makeEmployeeWithShift($company);

        Holiday::create([
            'company_id' => $company->id,
            'date' => '2026-03-09',
            'name' => 'Libur Nasional Test',
            'type' => 'national',
            'is_active' => true,
        ]);

        $date = Carbon::parse('2026-03-09');

        $response = $this->actingAs($admin)->getJson('/api/attendance-report?'.http_build_query([
            'date_from' => $date->toDateString(),
            'date_to' => $date->toDateString(),
            'employee_id' => $employee->id,
        ]));

        $response->assertOk();
        $row = collect($response->json('data.data'))->firstWhere('employee.id', $employee->id);

        // Regression guard eksplisit: behavior holiday TIDAK berubah oleh
        // perubahan Fase 1 ini.
        $this->assertSame(0, $row['absent_days']);
        $this->assertSame(0, $row['expected_working_days']);
    }

    // ---------- 10: dailyRecap ----------

    public function test_daily_recap_shows_leave_status_for_approved_leave(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $admin = Employee::factory()->create(['company_id' => $company->id])->user;
        $admin->assignRole('admin');

        $employee = $this->makeEmployeeWithShift($company);
        $leaveType = $this->makeLeaveType($company);

        $date = Carbon::parse('2026-03-09');
        $this->makeLeaveRequest($employee, $leaveType, $date, $date, 'approved');

        $response = $this->actingAs($admin)->getJson("/api/attendance-report/employees/{$employee->id}/daily?".http_build_query([
            'date_from' => $date->toDateString(),
            'date_to' => $date->toDateString(),
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.0.status', 'leave');
    }

    // ---------- 11: company isolation ----------

    public function test_leave_in_one_company_does_not_affect_another_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $employeeA = Employee::factory()->create(['company_id' => $companyA->id]);
        $employeeB = Employee::factory()->create(['company_id' => $companyB->id]);
        $leaveTypeA = $this->makeLeaveType($companyA);

        $date = Carbon::parse('2026-03-11');
        $this->makeLeaveRequest($employeeA, $leaveTypeA, $date, $date, 'approved');

        $checker = app(LeaveCheckerInterface::class);

        $this->assertTrue($checker->isOnLeave($employeeA->id, $date));
        $this->assertFalse($checker->isOnLeave($employeeB->id, $date));
    }

    // ---------- 12: employee tanpa working schedule ----------

    public function test_employee_without_working_schedule_is_safe(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $admin = Employee::factory()->create(['company_id' => $company->id])->user;
        $admin->assignRole('admin');

        // Sengaja TIDAK panggil makeEmployeeWithShift() -- employee ini
        // tidak punya working_schedule_id maupun assignment apapun.
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $date = Carbon::parse('2026-03-09');

        $response = $this->actingAs($admin)->getJson('/api/attendance-report?'.http_build_query([
            'date_from' => $date->toDateString(),
            'date_to' => $date->toDateString(),
            'employee_id' => $employee->id,
        ]));

        $response->assertOk();
        $row = collect($response->json('data.data'))->firstWhere('employee.id', $employee->id);

        // Tidak ada working schedule -> expectedShiftsByDate() balikin array
        // kosong -> expected_working_days=0 -> tidak ada absence yang bisa
        // "salah" dihitung, dan tidak boleh error/exception.
        $this->assertSame(0, $row['expected_working_days']);
        $this->assertSame(0, $row['absent_days']);
        $this->assertNull($row['attendance_rate']);
    }

    // ---------- 13: half-day -- ikuti behavior existing, JANGAN desain baru ----------

    public function test_half_day_leave_follows_existing_date_range_behavior_without_new_logic(): void
    {
        // Tidak ada mekanisme half-day-aware di LeaveCheckerInterface atau
        // di manapun pada codebase existing (HalfDaySession enum ada tapi
        // tidak pernah dipakai buat narrow-kan pengecekan attendance/absence
        // manapun). Sesuai instruksi eksplisit, DatabaseLeaveChecker TIDAK
        // menambahkan logic baru untuk half-day -- perilakunya sama seperti
        // leave full-day: tanggal dalam [start_date, end_date] = on-leave,
        // titik. Test ini mendokumentasikan itu apa adanya, bukan
        // memvalidasi desain half-day yang belum ada.
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $leaveType = $this->makeLeaveType($company);

        $date = Carbon::parse('2026-03-11');
        $this->makeLeaveRequest($employee, $leaveType, $date, $date, 'approved', isHalfDay: true);

        $checker = app(LeaveCheckerInterface::class);

        $this->assertTrue($checker->isOnLeave($employee->id, $date));
    }
}