<?php

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Exceptions\AttendanceValidationException;
use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Attendance\Services\AttendanceService;
use Illuminate\Http\Request;

class AttendanceDeviceApiController extends Controller
{
    public function __construct(private AttendanceService $attendanceService)
    {
    }

    public function clockIn(Request $request)
    {
        $device = $this->device($request);
        $employeeCode = $request->validate(['employee_code' => ['required', 'string']])['employee_code'];

        try {
            $attendance = $this->attendanceService->clockInForDevice($device, $employeeCode);

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
        $device = $this->device($request);
        $employeeCode = $request->validate(['employee_code' => ['required', 'string']])['employee_code'];

        try {
            $attendance = $this->attendanceService->clockOutForDevice($device, $employeeCode);

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
        $device = $this->device($request);
        $employeeCode = $request->validate(['employee_code' => ['required', 'string']])['employee_code'];

        try {
            $data = $this->attendanceService->todayForDevice($device, $employeeCode);

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

    public function todayByFace(Request $request)
    {
        $device = $this->device($request);
        $imageBase64 = $request->validate(['image_base64' => ['required', 'string']])['image_base64'];

        try {
            $data = $this->attendanceService->todayForDeviceByFace($device, $imageBase64);

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

    public function clockInByFace(Request $request)
    {
        $device = $this->device($request);
        $imageBase64 = $request->validate(['image_base64' => ['required', 'string']])['image_base64'];

        try {
            $attendance = $this->attendanceService->clockInForDeviceByFace($device, $imageBase64);

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

    public function clockOutByFace(Request $request)
    {
        $device = $this->device($request);
        $imageBase64 = $request->validate(['image_base64' => ['required', 'string']])['image_base64'];

        try {
            $attendance = $this->attendanceService->clockOutForDeviceByFace($device, $imageBase64);

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

    private function device(Request $request): AttendanceDevice
    {
        return $request->attributes->get('attendance_device');
    }
}