<?php

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Exceptions\AttendanceValidationException;
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
        ]);

        try {
            $attendance = $this->attendanceService->clockIn(
                $request->user(),
                $validated['latitude'] ?? null,
                $validated['longitude'] ?? null,
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
        ]);

        try {
            $attendance = $this->attendanceService->clockOut(
                $request->user(),
                $validated['latitude'] ?? null,
                $validated['longitude'] ?? null,
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
}