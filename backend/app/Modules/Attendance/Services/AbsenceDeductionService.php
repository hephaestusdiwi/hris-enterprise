<?php

namespace App\Modules\Attendance\Services;

use App\Models\User;
use App\Modules\Attendance\Contracts\HolidayCheckerInterface;
use App\Modules\Attendance\Contracts\LeaveCheckerInterface;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Employee\Models\Employee;
use App\Modules\LeaveBalance\Models\LeaveBalance;
use App\Modules\LeaveBalance\Services\LeaveBalanceEligibilityService;
use App\Modules\LeaveBalance\Support\LeaveBalanceMath;
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

    /**
     * "Time-Off / Deduction History" -- daftar LeaveRequest hasil Absence
     * Deduction (approved MAUPUN reversed, biar HR bisa lihat histori
     * lengkap, bukan cuma yang masih aktif). Dibutuhkan supaya HR punya
     * cara balik lihat & reverse deduction yang sudah dibuat -- listAbsences()
     * di atas SENGAJA gak bisa dipakai buat ini karena begitu tanggal jadi
     * leave, dia otomatis hilang dari situ (absence = computed, bukan row).
     *
     * @param Collection<int, Employee> $employees
     */
    public function listDeductions(Collection $employees, Carbon $dateFrom, Carbon $dateTo): Collection
    {
        return LeaveRequest::with(['employee', 'leaveType'])
            ->where('source', LeaveRequestSource::AbsenceDeduction->value)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('start_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->latest('id')
            ->get();
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

    /**
     * Reversal/"Correction/Clear" ala Talenta -- SENGAJA cuma berlaku buat
     * source=absence_deduction (bukan generic Leave cancellation). Guard +
     * mutation SEMUA terjadi di dalam satu transaction, di atas instance
     * yang di-lockForUpdate() -- supaya 2 reversal request yang datang
     * nyaris bersamaan untuk LeaveRequest yang sama, cuma SATU yang bisa
     * berhasil (yang kedua akan nemuin status udah bukan Approved lagi,
     * bukan double-subtract used_days).
     */
    public function reverse(LeaveRequest $leaveRequest, User $actor, ?string $reason = null): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $actor, $reason) {
            // Re-fetch + lock instance SEGAR di dalam transaction -- jangan
            // percaya attribute dari $leaveRequest yang di-pass masuk (bisa
            // stale kalau ada request lain yang barusan ubah row ini).
            $locked = LeaveRequest::where('id', $leaveRequest->id)->lockForUpdate()->firstOrFail();

            if ($locked->source !== LeaveRequestSource::AbsenceDeduction) {
                throw new LeaveRequestValidationException('Hanya LeaveRequest hasil Absence Deduction yang bisa di-reverse lewat endpoint ini.');
            }

            if ($locked->status !== LeaveRequestStatus::Approved) {
                throw new LeaveRequestValidationException('Hanya LeaveRequest berstatus approved yang bisa di-reverse.');
            }

            if ($locked->leave_balance_id) {
                $balance = LeaveBalance::where('id', $locked->leave_balance_id)->lockForUpdate()->firstOrFail();

                // Balance safety -- jangan pernah menghasilkan used_days
                // negatif. gte() sudah ada di LeaveBalanceMath, direuse
                // apa adanya.
                if (! LeaveBalanceMath::gte((string) $balance->used_days, (string) $locked->total_days)) {
                    throw new LeaveRequestValidationException('Balance tidak konsisten -- used_days lebih kecil dari total_days yang mau dikembalikan.');
                }

                $balance->update([
                    'used_days' => LeaveBalanceMath::sub((string) $balance->used_days, (string) $locked->total_days),
                ]);
            }
            // leave_balance_id null (requires_balance=false saat deduction
            // dibuat) -> SENGAJA tidak cari balance baru, tidak mutasi
            // apapun, cuma reverse status LeaveRequest-nya.

            $locked->update([
                'status' => LeaveRequestStatus::Reversed->value,
                'reversed_by_user_id' => $actor->id,
                'reversed_at' => now(),
                'reversal_reason' => $reason,
            ]);

            // LeaveRequest TIDAK dihapus (soft delete pun tidak dipakai) --
            // history tetap utuh, source tetap 'absence_deduction'. Begitu
            // status != approved, DatabaseLeaveChecker::isOnLeave() otomatis
            // return false untuk tanggal ini lagi -> AttendanceReportService
            // otomatis anggap tanggal ini absent lagi, TANPA perlu menyentuh
            // Attendance row apapun (absence tetap computed result).
            return $locked->fresh(['leaveType', 'leaveBalance']);
        });
    }
}