<?php

namespace App\Modules\AttendanceRequest\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AttendanceRequest\Exceptions\AttendanceRequestValidationException;
use App\Modules\AttendanceRequest\Models\AttendanceRequest;
use App\Modules\AttendanceRequest\Requests\StoreAttendanceRequestRequest;
use App\Modules\AttendanceRequest\Services\AttendanceRequestService;
use Illuminate\Http\Request;

class AttendanceRequestController extends Controller
{
    public function __construct(private AttendanceRequestService $service)
    {
    }

    public function index(Request $request)
    {
        $requests = AttendanceRequest::with(['employee', 'attachments', 'shift'])
            ->when($request->query('employee_id'), fn ($q, $v) => $q->where('employee_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate(20);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $requests]);
    }

    public function myRequests(Request $request)
    {
        $employee = $request->user()->employee;

        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee.');

        $requests = AttendanceRequest::with(['attachments', 'shift', 'attendance'])
            ->where('employee_id', $employee->id)
            ->latest()
            ->paginate(20);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $requests]);
    }

    public function store(StoreAttendanceRequestRequest $request)
    {
        $employee = $request->user()->employee;

        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee.');

        try {
            $attendanceRequest = $this->service->submit($employee, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Attendance request berhasil diajukan',
                'data' => $attendanceRequest->load(['attachments', 'shift', 'attendance']),
            ], 201);
        } catch (AttendanceRequestValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function cancel(Request $request, AttendanceRequest $attendanceRequest)
    {
        $employee = $request->user()->employee;

        abort_if(
            ! $employee || $attendanceRequest->employee_id !== $employee->id,
            403,
            'Anda tidak berhak membatalkan attendance request ini.'
        );

        try {
            $attendanceRequest = $this->service->cancel($attendanceRequest);

            return response()->json([
                'success' => true,
                'message' => 'Attendance request berhasil dibatalkan',
                'data' => $attendanceRequest,
            ]);
        } catch (AttendanceRequestValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }
}
