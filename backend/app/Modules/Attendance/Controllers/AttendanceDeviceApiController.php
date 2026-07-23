<?php

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Enums\AttendanceIdentificationMethod;
use App\Modules\Attendance\Exceptions\AttendanceValidationException;
use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Attendance\Services\AttendanceService;
use Illuminate\Http\Request;

class AttendanceDeviceApiController extends Controller
{
    public function __construct(private AttendanceService $attendanceService)
    {
    }

    public function today(Request $request)
    {
        $employeeCode = $request->validate(['employee_code' => ['required', 'string']])['employee_code'];

        return $this->respondToday($request, AttendanceIdentificationMethod::EmployeeCode, ['employee_code' => $employeeCode]);
    }

    public function clockIn(Request $request)
    {
        $employeeCode = $request->validate(['employee_code' => ['required', 'string']])['employee_code'];

        return $this->respondClockIn($request, AttendanceIdentificationMethod::EmployeeCode, ['employee_code' => $employeeCode]);
    }

    public function clockOut(Request $request)
    {
        $employeeCode = $request->validate(['employee_code' => ['required', 'string']])['employee_code'];

        return $this->respondClockOut($request, AttendanceIdentificationMethod::EmployeeCode, ['employee_code' => $employeeCode]);
    }

    public function todayByFace(Request $request)
    {
        $imageBase64 = $request->validate(['image_base64' => ['required', 'string']])['image_base64'];

        return $this->respondToday($request, AttendanceIdentificationMethod::Face, ['image_base64' => $imageBase64]);
    }

    public function clockInByFace(Request $request)
    {
        $imageBase64 = $request->validate(['image_base64' => ['required', 'string']])['image_base64'];

        return $this->respondClockIn($request, AttendanceIdentificationMethod::Face, ['image_base64' => $imageBase64]);
    }

    public function clockOutByFace(Request $request)
    {
        $imageBase64 = $request->validate(['image_base64' => ['required', 'string']])['image_base64'];

        return $this->respondClockOut($request, AttendanceIdentificationMethod::Face, ['image_base64' => $imageBase64]);
    }

    public function todayByQr(Request $request)
    {
        $qrToken = $request->validate(['qr_token' => ['required', 'string']])['qr_token'];

        return $this->respondToday($request, AttendanceIdentificationMethod::Qr, ['qr_token' => $qrToken]);
    }

    public function clockInByQr(Request $request)
    {
        $qrToken = $request->validate(['qr_token' => ['required', 'string']])['qr_token'];

        return $this->respondClockIn($request, AttendanceIdentificationMethod::Qr, ['qr_token' => $qrToken]);
    }

    public function clockOutByQr(Request $request)
    {
        $qrToken = $request->validate(['qr_token' => ['required', 'string']])['qr_token'];

        return $this->respondClockOut($request, AttendanceIdentificationMethod::Qr, ['qr_token' => $qrToken]);
    }

    public function officeQr(Request $request)
    {
        $device = $this->device($request);
        $result = $this->attendanceService->generateOfficeQr($device);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $result,
        ]);
    }

    private function respondToday(Request $request, AttendanceIdentificationMethod $method, array $payload)
    {
        try {
            $data = $this->attendanceService->todayForDeviceUsing($this->device($request), $method, $payload);

            return response()->json(['success' => true, 'message' => 'OK', 'data' => $data]);
        } catch (AttendanceValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    private function respondClockIn(Request $request, AttendanceIdentificationMethod $method, array $payload)
    {
        try {
            $attendance = $this->attendanceService->clockInForDeviceUsing($this->device($request), $method, $payload);

            return response()->json(['success' => true, 'message' => 'Clock-in berhasil', 'data' => $attendance], 201);
        } catch (AttendanceValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    private function respondClockOut(Request $request, AttendanceIdentificationMethod $method, array $payload)
    {
        try {
            $attendance = $this->attendanceService->clockOutForDeviceUsing($this->device($request), $method, $payload);

            return response()->json(['success' => true, 'message' => 'Clock-out berhasil', 'data' => $attendance]);
        } catch (AttendanceValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    private function device(Request $request): AttendanceDevice
    {
        return $request->attributes->get('attendance_device');
    }
}