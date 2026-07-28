<?php

namespace App\Modules\LeaveRequest\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\LeaveRequest\Exceptions\LeaveRequestValidationException;
use App\Modules\LeaveRequest\Models\LeaveRequest;
use App\Modules\LeaveRequest\Requests\StoreLeaveRequestRequest;
use App\Modules\LeaveRequest\Services\LeaveRequestService;
use App\Modules\LeaveType\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LeaveRequestController extends Controller
{
    public function __construct(private LeaveRequestService $leaveRequestService)
    {
    }

    public function index(Request $request)
    {
        $leaveRequests = LeaveRequest::with(['employee', 'leaveType', 'leaveBalance'])
            ->when($request->query('employee_id'), fn ($q, $v) => $q->where('employee_id', $v))
            ->when($request->query('leave_type_id'), fn ($q, $v) => $q->where('leave_type_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate(20);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $leaveRequests]);
    }

    public function myRequests(Request $request)
    {
        $employee = $request->user()->employee;

        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee.');

        $leaveRequests = LeaveRequest::with(['leaveType', 'leaveBalance'])
            ->where('employee_id', $employee->id)
            ->latest()
            ->paginate(20);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $leaveRequests]);
    }

    public function store(StoreLeaveRequestRequest $request)
    {
        $employee = $request->user()->employee;

        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee.');

        $leaveType = LeaveType::findOrFail($request->validated('leave_type_id'));

        try {
            $leaveRequest = $this->leaveRequestService->submit($employee, $leaveType, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Leave request berhasil diajukan',
                'data' => $leaveRequest->load(['leaveType', 'leaveBalance']),
            ], 201);
        } catch (LeaveRequestValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest)
    {
        $employee = $request->user()->employee;

        abort_if(! $employee || $leaveRequest->employee_id !== $employee->id, 403, 'Anda tidak berhak membatalkan leave request ini.');

        try {
            $leaveRequest = $this->leaveRequestService->cancel($leaveRequest);

            return response()->json(['success' => true, 'message' => 'Leave request berhasil dibatalkan', 'data' => $leaveRequest]);
        } catch (LeaveRequestValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function uploadAttachment(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'max:5120']]);

        $path = $request->file('file')->store('leave-attachments', 'public');

        return response()->json(['success' => true, 'message' => 'OK', 'data' => ['path' => $path]], 201);
    }
}