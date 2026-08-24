<?php

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Contracts\HolidayCheckerInterface;
use App\Modules\Attendance\Contracts\LeaveCheckerInterface;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Employee\Models\Employee;
use App\Modules\LeaveBalance\Services\LeaveBalanceEligibilityService;
use App\Modules\LeaveRequest\Enums\LeaveRequestSource;
use App\Modules\LeaveRequest\Enums\LeaveRequestStatus;
use App\Modules\LeaveRequest\Exceptions\LeaveRequestValidationException;
use App\Modules\LeaveRequest\Models\LeaveRequest;
use App\Modules\LeaveRequest\Services\LeaveApprovalService;
use App\Modules\LeaveRequest\Services\LeaveRequestService;
use App\Modules\LeaveType\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * STEP B Fase 2 -- Absence Deduction workflow. Attendance absence tetap
 * computed result (lihat AttendanceReportService::listAbsences()) -- tidak
 * ada tabel absence baru. LeaveRequest tetap satu-satunya source of truth
 * untuk record time-off resmi (termasuk yang berasal dari absence
 * deduction), dan LeaveApprovalService::autoApprove() tetap satu-satunya
 * tempat yang memutasi LeaveBalance.used_days -- service ini SENGAJA tidak
 * duplicate logic mutasi balance apapun.
 */
class AbsenceDeductionService
{
    public function __construct(
        private AttendanceReportService $reportService,
        private HolidayCheckerInterface $holidayChecker,
        private LeaveCheckerInterface $leaveChecker,
        private LeaveBalanceEligibilityService $eligibilityService,
        private LeaveRequestService $leaveRequestService,
        private LeaveApprovalService $leaveApprovalService,
    ) {
    }

    /**
     * @param Collection<int, Employee> $employees
     * @return array<int, array{employee: array, date: string, status: string}>
     */
    public function listAbsences(Collection $employees, Carbon $dateFrom, Carbon $dateTo): array
    {
        return $this->reportService->listAbsences($employees, $dateFrom, $dateTo);
    }

    public function markAsTimeOff(Employee $employee, LeaveType $leaveType, Carbon $date): LeaveRequest
    {
        // Company isolation (validation item #2, #4): LeaveType yang dipakai
        // WAJIB satu company dengan employee-nya. Ini pengecekan konkret &
        // testable -- lihat catatan desain: role admin/hr di sistem ini
        // memang sengaja full cross-company visibility (EmployeePolicy),
        // jadi "isolasi" yang bisa ditegakkan di sini adalah konsistensi
        // ANTAR entity yang terlibat (employee vs leave type), bukan
        // "company milik actor" yang bukan konsep yang ada di sistem ini.
        if ($leaveType->company_id !== $employee->company_id) {
            throw new LeaveRequestValidationException('Leave type ini bukan milik company employee tersebut.');
        }

        // Validation item #10: eligibility rules existing (masa kerja,
        // gender, status aktif/resign) tetap berlaku, direuse apa adanya.
        if (! $this->eligibilityService->meetsServiceRequirement($employee, $leaveType, $date)) {
            throw new LeaveRequestValidationException('Employee belum/tidak lagi memenuhi syarat untuk leave type ini.');
        }

        // Validation item #6: tanggal holiday bukan absence.
        if ($this->holidayChecker->isHoliday($employee->company_id, $employee->branch_id, $date)) {
            throw new LeaveRequestValidationException('Tanggal ini hari libur, bukan absence.');
        }

        // Validation item #7: employee sudah punya Attendance record di
        // tanggal itu -> bukan absence (mereka sudah hadir/berstatus lain).
        $hasAttendance = Attendance::where('employee_id', $employee->id)
            ->where('attendance_date', $date->toDateString())
            ->exists();

        if ($hasAttendance) {
            throw new LeaveRequestValidationException('Employee sudah memiliki attendance record pada tanggal ini, bukan absence.');
        }

        // Sudah berstatus leave (approved) -> bukan absence juga.
        if ($this->leaveChecker->isOnLeave($employee->id, $date)) {
            throw new LeaveRequestValidationException('Employee sudah berstatus cuti approved pada tanggal ini.');
        }

        return DB::transaction(function () use ($employee, $leaveType, $date) {
            // Validation item #8 -- reuse guard yang SAMA PERSIS dengan
            // submission normal (LeaveRequestService::assertNoOverlap, kini
            // public). Ini juga mekanisme utama anti double-deduction
            // (idempotency) -- request kedua untuk tanggal yang sama akan
            // exception di sini, TANPA butuh unique constraint baru.
            $this->leaveRequestService->assertNoOverlap($employee, $date, $date);

            $leaveBalance = null;

            if ($leaveType->requires_balance) {
                // Validation item #9. findBalance() & assertSufficientBalance()
                // sama persis dengan yang dipakai submission normal.
                $leaveBalance = $this->leaveRequestService->findBalance($employee, $leaveType, $date);

                if (! $leaveBalance) {
                    throw new LeaveRequestValidationException('Leave balance tidak ditemukan untuk periode ini.');
                }

                $this->leaveRequestService->assertSufficientBalance($leaveBalance, '1.00');
            }

            $leaveRequest = LeaveRequest::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'leave_balance_id' => $leaveBalance?->id,
                'start_date' => $date->toDateString(),
                'end_date' => $date->toDateString(),
                'is_half_day' => false,
                'total_days' => '1.00',
                'reason' => 'Absence deduction oleh HR/Admin',
                'status' => LeaveRequestStatus::Pending->value, // langsung di-flip oleh autoApprove() di bawah
                'source' => LeaveRequestSource::AbsenceDeduction->value,
                'requested_at' => now(),
            ]);

            // Design decision LOCKED: SELALU autoApprove(), skip branching
            // requires_approval milik LeaveType sepenuhnya -- keputusan HR
            // di sini SUDAH final approval. Method ini juga satu-satunya
            // tempat used_days termutasi (lewat leave_balance_id di atas) --
            // tidak ada duplicate logic mutasi balance di sini.
            $this->leaveApprovalService->autoApprove($leaveRequest);

            return $leaveRequest->fresh(['leaveType', 'leaveBalance']);
        });
    }
}