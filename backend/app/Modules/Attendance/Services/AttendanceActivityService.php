<?php

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Enums\AttendanceActivityType;
use App\Modules\Attendance\Models\AttendanceActivity;

/**
 * Satu titik masuk buat mencatat Attendance Activity. SENGAJA sederhana --
 * bukan generic audit/event framework, cuma satu method create() yang
 * dipanggil manual & sinkron dari titik-titik write-path yang sudah diaudit
 * (AttendanceService, AttendanceApprovalService, AttendanceRequestService,
 * AttendanceRequestApprovalService, AttendanceController).
 *
 * Tidak ada try-catch di sini secara sengaja: kalau recording gagal, itu
 * harus kelihatan (exception bubble up), bukan gagal diam-diam -- state
 * Attendance yang sudah berhasil disimpan sebelumnya (di caller) tidak akan
 * "terlihat sukses tanpa activity tercatat tanpa alasan jelas".
 */
class AttendanceActivityService
{
    public function record(
        int $employeeId,
        AttendanceActivityType $type,
        ?int $attendanceId = null,
        ?int $actorUserId = null,
        ?array $metadata = null,
    ): AttendanceActivity {
        return AttendanceActivity::create([
            'employee_id' => $employeeId,
            'attendance_id' => $attendanceId,
            'activity_type' => $type->value,
            'actor_user_id' => $actorUserId,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}