<?php

namespace App\Modules\AttendanceRequest\Services;

use App\Modules\Attendance\Models\Attendance;
use App\Modules\AttendanceRequest\Enums\AttendanceRequestStatus;
use App\Modules\AttendanceRequest\Exceptions\AttendanceRequestValidationException;
use App\Modules\AttendanceRequest\Models\AttendanceRequest;
use App\Modules\AttendanceRequest\Models\AttendanceRequestAttachment;
use App\Modules\Employee\Models\Employee;
use App\Modules\Shift\Models\Shift;
use App\Modules\WorkingSchedule\Contracts\WorkingScheduleResolverInterface;
use App\Modules\WorkingSchedule\Models\WorkingScheduleDetail;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;

class AttendanceRequestService
{
    public function __construct(
        private WorkingScheduleResolverInterface $workingScheduleResolver,
        private AttendanceRequestApprovalService $approvalService,
    ) {
    }

    /**
     * @param array{attendance_date: string, requested_clock_in?: ?string, requested_clock_out?: ?string,
     *              reason: string, attachments?: array<int, UploadedFile>} $data
     */
    public function submit(Employee $employee, array $data): AttendanceRequest
    {
        $attendanceDate = Carbon::parse($data['attendance_date'])->startOfDay();

        $existingAttendance = Attendance::where('employee_id', $employee->id)
            ->where('attendance_date', $attendanceDate->toDateString())
            ->first();

        // Kalau attendance belum ada sama sekali, requested_clock_in wajib
        // diisi -- tidak masuk akal membuat Attendance baru cuma dari
        // clock-out saja (kolom `status` di tabel attendances NOT NULL dan
        // harus dihitung dari clock-in).
        if (! $existingAttendance && empty($data['requested_clock_in'])) {
            throw new AttendanceRequestValidationException(
                'Attendance untuk tanggal ini belum ada sama sekali, requested clock-in wajib diisi.'
            );
        }

        $this->assertNoPendingDuplicate($employee, $attendanceDate);

        $shift = $this->resolveShiftForDate($employee, $attendanceDate);

        if (! $shift) {
            throw new AttendanceRequestValidationException(
                'Employee tidak memiliki shift pada tanggal ini, attendance request tidak dapat diajukan.'
            );
        }

        $attendanceRequest = AttendanceRequest::create([
            'employee_id' => $employee->id,
            'attendance_id' => $existingAttendance?->id,
            'attendance_date' => $attendanceDate->toDateString(),
            'shift_id' => $shift->id,
            'requested_clock_in' => $data['requested_clock_in'] ?? null,
            'requested_clock_out' => $data['requested_clock_out'] ?? null,
            'reason' => $data['reason'],
            'status' => AttendanceRequestStatus::Pending->value,
            'submitted_at' => now(),
        ]);

        foreach ($data['attachments'] ?? [] as $file) {
            if ($file instanceof UploadedFile) {
                $this->storeAttachment($attendanceRequest, $file);
            }
        }

        $this->approvalService->initiate($attendanceRequest);

        return $attendanceRequest->fresh(['attachments', 'shift', 'attendance']);
    }

    public function cancel(AttendanceRequest $attendanceRequest): AttendanceRequest
    {
        if ($attendanceRequest->status !== AttendanceRequestStatus::Pending) {
            throw new AttendanceRequestValidationException('Hanya attendance request berstatus pending yang bisa dibatalkan.');
        }

        $attendanceRequest->update([
            'status' => AttendanceRequestStatus::Cancelled->value,
            'decided_at' => now(),
        ]);

        $this->approvalService->cancelApprovalIfAny($attendanceRequest);

        return $attendanceRequest->fresh();
    }

    private function assertNoPendingDuplicate(Employee $employee, Carbon $attendanceDate): void
    {
        $exists = AttendanceRequest::where('employee_id', $employee->id)
            ->where('attendance_date', $attendanceDate->toDateString())
            ->where('status', AttendanceRequestStatus::Pending->value)
            ->exists();

        if ($exists) {
            throw new AttendanceRequestValidationException('Sudah ada attendance request berstatus pending untuk tanggal ini.');
        }
    }

    /**
     * Duplikasi kecil dan sengaja dari AttendanceService::resolveShiftForToday,
     * cuma diparametrisasi by date (bukan cuma "hari ini"). Tidak menyentuh
     * AttendanceService supaya behavior clock-in/out normal tidak berubah
     * sedikit pun.
     */
    private function resolveShiftForDate(Employee $employee, Carbon $date): ?Shift
    {
        $workingScheduleId = $this->workingScheduleResolver->resolveWorkingScheduleId($employee);

        if (! $workingScheduleId) {
            return null;
        }

        $detail = WorkingScheduleDetail::where('working_schedule_id', $workingScheduleId)
            ->where('day_of_week', $date->dayOfWeekIso)
            ->first();

        return $detail?->shift;
    }

    private function storeAttachment(AttendanceRequest $attendanceRequest, UploadedFile $file): void
    {
        $path = $file->store("attendance-request-attachments/{$attendanceRequest->employee_id}", 'public');

        AttendanceRequestAttachment::create([
            'attendance_request_id' => $attendanceRequest->id,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
        ]);
    }
}