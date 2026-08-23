<?php

namespace Tests\Feature\AttendanceRequest;

use App\Models\User;
use App\Modules\ApprovalFlow\Enums\ApproverType;
use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\AttendanceRequest\Models\AttendanceRequest;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\Shift\Models\Shift;
use App\Modules\WorkingSchedule\Models\WorkingSchedule;
use App\Modules\WorkingSchedule\Models\WorkingScheduleDetail;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendanceRequestSubmissionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bikin Employee lengkap dengan shift aktif di hari `$attendanceDate`
     * (lewat WorkingSchedule + WorkingScheduleDetail + override langsung
     * di employee.working_schedule_id -- prioritas #1 di
     * WorkingScheduleResolver).
     */
    private function makeEmployeeWithShift(Carbon $attendanceDate, array $shiftOverrides = []): Employee
    {
        $company = Company::factory()->create();
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
     * Tanpa ApprovalFlow untuk approval_type='attendance_request',
     * AttendanceRequestApprovalService::initiate() auto-approve request
     * seketika (lihat applyApproval()) -- ini production behavior yang
     * memang intended (bukan bug), jadi assertion di sini disinkronkan ke
     * status akhir yang benar: 'approved', dengan Attendance baru langsung
     * dibuat & attendance_id ter-set (bukan null seperti asumsi lama saat
     * status masih 'pending').
     */
    public function test_employee_can_submit_clock_in_only_request(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $date = now()->subDay()->startOfDay();
        $employee = $this->makeEmployeeWithShift($date);
        $employee->user->assignRole('employee');

        $response = $this->actingAs($employee->user)->postJson('/api/attendance-requests', [
            'attendance_date' => $date->toDateString(),
            'requested_clock_in' => $date->copy()->setTime(8, 0)->toDateTimeString(),
            'reason' => 'Sistem tidak dapat diakses pagi itu',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseCount('attendance_requests', 1);
        $attendanceRequest = AttendanceRequest::first();
        $this->assertNotNull($attendanceRequest->attendance_id);

        $attendance = Attendance::find($attendanceRequest->attendance_id);
        $this->assertNotNull($attendance);
        $this->assertSame($employee->id, $attendance->employee_id);
        $this->assertSame('08:00:00', $attendance->clock_in->format('H:i:s'));
        $this->assertSame('attendance_request', $attendance->clock_in_method);
    }

    public function test_employee_can_submit_clock_out_only_request_when_attendance_already_exists(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $date = now()->subDay()->startOfDay();
        $employee = $this->makeEmployeeWithShift($date);
        $employee->user->assignRole('employee');

        $shift = Shift::where('company_id', $employee->company_id)->firstOrFail();

        $attendance = Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => $date->toDateString(),
            'shift_id' => $shift->id,
            'clock_in' => $date->copy()->setTime(8, 0),
            'status' => 'present',
        ]);

        $response = $this->actingAs($employee->user)->postJson('/api/attendance-requests', [
            'attendance_date' => $date->toDateString(),
            'requested_clock_out' => $date->copy()->setTime(17, 0)->toDateTimeString(),
            'reason' => 'Lupa tap clock-out',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('attendance_requests', [
            'employee_id' => $employee->id,
            'attendance_id' => $attendance->id,
        ]);
    }

    public function test_employee_can_submit_clock_in_and_clock_out_request_together(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $date = now()->subDay()->startOfDay();
        $employee = $this->makeEmployeeWithShift($date);
        $employee->user->assignRole('employee');

        $response = $this->actingAs($employee->user)->postJson('/api/attendance-requests', [
            'attendance_date' => $date->toDateString(),
            'requested_clock_in' => $date->copy()->setTime(8, 0)->toDateTimeString(),
            'requested_clock_out' => $date->copy()->setTime(17, 0)->toDateTimeString(),
            'reason' => 'Listrik mati seharian, device attendance tidak bisa dipakai',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('attendance_requests', 1);
    }

    /**
     * Attendance belum ada sama sekali untuk tanggal ini -> requested
     * clock-in WAJIB diisi. Kalau cuma clock-out saja, tolak dengan pesan
     * yang jelas (bukan bikin Attendance ganjil tanpa clock-in).
     */
    public function test_request_blocked_when_attendance_missing_and_only_clock_out_provided(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $date = now()->subDay()->startOfDay();
        $employee = $this->makeEmployeeWithShift($date);
        $employee->user->assignRole('employee');

        $response = $this->actingAs($employee->user)->postJson('/api/attendance-requests', [
            'attendance_date' => $date->toDateString(),
            'requested_clock_out' => $date->copy()->setTime(17, 0)->toDateTimeString(),
            'reason' => 'Lupa clock in dan clock out, attendance belum pernah ada',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('attendance_requests', 0);
    }

    public function test_request_blocked_when_employee_has_no_shift_on_that_date(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]); // sengaja TIDAK di-assign working_schedule
        $employee->user->assignRole('employee');

        $date = now()->subDay()->startOfDay();

        $response = $this->actingAs($employee->user)->postJson('/api/attendance-requests', [
            'attendance_date' => $date->toDateString(),
            'requested_clock_in' => $date->copy()->setTime(8, 0)->toDateTimeString(),
            'reason' => 'Tidak ada shift terpasang',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('attendance_requests', 0);
    }

    /**
     * Business rule "duplicate pending request ditolak" cuma bisa
     * diverifikasi kalau request PERTAMA beneran berstatus pending saat
     * request KEDUA masuk. Tanpa ApprovalFlow, request pertama auto-approve
     * seketika (bukan pending lagi), jadi guard-nya otomatis tidak
     * ketrigger -- itu bukan berarti rule-nya hilang, cuma skenario test
     * tanpa flow tidak representatif buat rule ini. Guard aslinya
     * (assertNoPendingDuplicate di AttendanceRequestService) TIDAK disentuh
     * sama sekali di sini, cuma fixture-nya dilengkapi ApprovalFlow supaya
     * skenario "ada pending duplicate" beneran ke-exercise.
     */
    public function test_duplicate_pending_request_for_same_date_is_blocked(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $date = now()->subDay()->startOfDay();
        $employee = $this->makeEmployeeWithShift($date);
        $employee->user->assignRole('employee');

        $approver = Employee::factory()->create(['company_id' => $employee->company_id]);
        ApprovalFlow::create([
            'company_id' => $employee->company_id,
            'name' => 'Default Flow',
            'code' => 'DEFAULT-'.$employee->company_id,
            'approval_type' => 'attendance_request',
            'is_active' => true,
        ])->steps()->create([
            'sequence' => 1,
            'approver_type' => ApproverType::SpecificEmployee->value,
            'approver_employee_id' => $approver->id,
            'is_active' => true,
        ]);

        $payload = [
            'attendance_date' => $date->toDateString(),
            'requested_clock_in' => $date->copy()->setTime(8, 0)->toDateTimeString(),
            'reason' => 'Percobaan pertama',
        ];

        $first = $this->actingAs($employee->user)->postJson('/api/attendance-requests', $payload);
        $first->assertStatus(201);
        $this->assertSame('pending', $first->json('data.status')); // ada approval flow -> tetap pending, bukan auto-approve

        $second = $this->actingAs($employee->user)->postJson('/api/attendance-requests', $payload + ['reason' => 'Percobaan kedua']);

        $second->assertStatus(422);
        $this->assertDatabaseCount('attendance_requests', 1);
    }

    public function test_role_without_create_permission_cannot_submit_request(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $date = now()->subDay()->startOfDay();
        $employee = $this->makeEmployeeWithShift($date);
        // Sengaja TIDAK assign role apapun -> tidak punya 'create attendance requests'.

        $response = $this->actingAs($employee->user)->postJson('/api/attendance-requests', [
            'attendance_date' => $date->toDateString(),
            'requested_clock_in' => $date->copy()->setTime(8, 0)->toDateTimeString(),
            'reason' => 'Tidak punya permission',
        ]);

        $response->assertStatus(403);
    }

    public function test_attachments_within_limit_are_stored(): void
    {
        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);

        $date = now()->subDay()->startOfDay();
        $employee = $this->makeEmployeeWithShift($date);
        $employee->user->assignRole('employee');

        $response = $this->actingAs($employee->user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/attendance-requests', [
                'attendance_date' => $date->toDateString(),
                'requested_clock_in' => $date->copy()->setTime(8, 0)->toDateTimeString(),
                'reason' => 'Ada 3 bukti pendukung',
                'attachments' => [
                    UploadedFile::fake()->create('bukti1.pdf', 200, 'application/pdf'),
                    UploadedFile::fake()->create('bukti2.jpg', 300, 'image/jpeg'),
                    UploadedFile::fake()->create('bukti3.csv', 50, 'text/csv'),
                ],
            ]);

        $response->assertStatus(201);

        $attendanceRequest = AttendanceRequest::first();
        $this->assertSame(3, $attendanceRequest->attachments()->count());

        foreach ($attendanceRequest->attachments as $attachment) {
            Storage::disk('public')->assertExists($attachment->file_path);
        }
    }

    public function test_more_than_5_attachments_is_rejected(): void
    {
        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);

        $date = now()->subDay()->startOfDay();
        $employee = $this->makeEmployeeWithShift($date);
        $employee->user->assignRole('employee');

        $files = [];
        for ($i = 1; $i <= 6; $i++) {
            $files[] = UploadedFile::fake()->create("bukti{$i}.pdf", 50, 'application/pdf');
        }

        $response = $this->actingAs($employee->user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/attendance-requests', [
                'attendance_date' => $date->toDateString(),
                'requested_clock_in' => $date->copy()->setTime(8, 0)->toDateTimeString(),
                'reason' => 'Melebihi batas 5 file',
                'attachments' => $files,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['attachments']);
        $this->assertDatabaseCount('attendance_requests', 0);
    }

    public function test_attachment_with_disallowed_mime_type_is_rejected(): void
    {
        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);

        $date = now()->subDay()->startOfDay();
        $employee = $this->makeEmployeeWithShift($date);
        $employee->user->assignRole('employee');

        $response = $this->actingAs($employee->user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/attendance-requests', [
                'attendance_date' => $date->toDateString(),
                'requested_clock_in' => $date->copy()->setTime(8, 0)->toDateTimeString(),
                'reason' => 'Format file tidak didukung',
                'attachments' => [
                    UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload'),
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['attachments.0']);
        $this->assertDatabaseCount('attendance_requests', 0);
    }

    public function test_attachment_larger_than_5mb_is_rejected(): void
    {
        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);

        $date = now()->subDay()->startOfDay();
        $employee = $this->makeEmployeeWithShift($date);
        $employee->user->assignRole('employee');

        $response = $this->actingAs($employee->user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/attendance-requests', [
                'attendance_date' => $date->toDateString(),
                'requested_clock_in' => $date->copy()->setTime(8, 0)->toDateTimeString(),
                'reason' => 'File kegedean',
                'attachments' => [
                    // 6000 KB ~ 6MB, melebihi batas 5MB (5120 KB)
                    UploadedFile::fake()->create('bukti-besar.pdf', 6000, 'application/pdf'),
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['attachments.0']);
        $this->assertDatabaseCount('attendance_requests', 0);
    }
}