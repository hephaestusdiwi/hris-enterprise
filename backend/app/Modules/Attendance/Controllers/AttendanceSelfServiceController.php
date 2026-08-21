<?php

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Exceptions\AttendanceValidationException;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Attendance\Services\AttendanceService;
use Illuminate\Http\Request;

class AttendanceSelfServiceController extends Controller
{
    public function __construct(private AttendanceService $attendanceService)
    {
    }

    public function clockIn(Request $request)
    {
        $validated = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'office_qr_token' => ['nullable', 'string'],
            'photo_base64' => ['nullable', 'string'],
        ]);

        try {
            $attendance = $this->attendanceService->clockIn(
                $request->user(),
                $validated['latitude'] ?? null,
                $validated['longitude'] ?? null,
                $validated['office_qr_token'] ?? null,
                $validated['photo_base64'] ?? null,
            );

            return response()->json([
                'success' => true,
                'message' => 'Clock-in berhasil',
                'data' => $attendance,
            ], 201);
        } catch (AttendanceValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }

    public function clockOut(Request $request)
    {
        $validated = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'office_qr_token' => ['nullable', 'string'],
            'photo_base64' => ['nullable', 'string'],
        ]);

        try {
            $attendance = $this->attendanceService->clockOut(
                $request->user(),
                $validated['latitude'] ?? null,
                $validated['longitude'] ?? null,
                $validated['office_qr_token'] ?? null,
                $validated['photo_base64'] ?? null,
            );

            return response()->json([
                'success' => true,
                'message' => 'Clock-out berhasil',
                'data' => $attendance,
            ]);
        } catch (AttendanceValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }

    public function today(Request $request)
    {
        try {
            $data = $this->attendanceService->today($request->user());

            return response()->json([
                'success' => true,
                'message' => 'OK',
                'data' => $data,
            ]);
        } catch (AttendanceValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }

    /**
     * Attendance History -- riwayat kehadiran milik employee yang login
     * sendiri. Reuse tabel `attendances` yang sudah ada, tidak ada tabel
     * baru. Pola sama seperti AttendanceRequestController::myRequests()
     * dan AnnouncementRecipientController::myAnnouncements().
     */
    public function myAttendances(Request $request)
    {
        $employee = $request->user()->employee;

        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee.');

        $attendances = Attendance::with(['shift'])
            ->where('employee_id', $employee->id)
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('date_from'), fn ($query, $dateFrom) => $query->where('attendance_date', '>=', $dateFrom))
            ->when($request->query('date_to'), fn ($query, $dateTo) => $query->where('attendance_date', '<=', $dateTo))
            ->latest('attendance_date')
            ->paginate(15);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $attendances]);
    }

    /**
     * Detail satu record attendance milik employee yang login sendiri.
     * Object-level check di sini: "apakah attendance ini punya dia",
     * BUKAN permission 'view attendances' (itu punya HR/Admin lewat
     * AttendanceController::show). Kalau bukan attendance miliknya, 403 --
     * employee tidak bisa lihat riwayat orang lain walau tau ID-nya.
     */
    public function show(Request $request, Attendance $attendance)
    {
        $employee = $request->user()->employee;

        abort_if(
            ! $employee || $attendance->employee_id !== $employee->id,
            403,
            'Anda tidak berhak melihat attendance ini.'
        );

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $attendance->load(['shift']),
        ]);
    }
}