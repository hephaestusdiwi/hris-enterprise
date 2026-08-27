<?php

namespace App\Modules\OvertimeRequest\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\OvertimeRequest\Exceptions\OvertimeRequestValidationException;
use App\Modules\OvertimeRequest\Models\OvertimeRequest;
use App\Modules\OvertimeRequest\Requests\StoreOvertimeRequestRequest;
use App\Modules\OvertimeRequest\Services\OvertimeRequestService;
use Illuminate\Http\Request;

class OvertimeRequestController extends Controller
{
    public function __construct(private OvertimeRequestService $service)
    {
    }

    public function index(Request $request)
    {
        $overtimeRequests = OvertimeRequest::with(['employee', 'attendance'])
            ->when($request->query('employee_id'), fn ($q, $v) => $q->where('employee_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('date_from'), fn ($q, $v) => $q->where('attendance_date', '>=', $v))
            ->when($request->query('date_to'), fn ($q, $v) => $q->where('attendance_date', '<=', $v))
            ->latest('attendance_date')
            ->paginate(15);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $overtimeRequests]);
    }

    public function myRequests(Request $request)
    {
        $employee = $request->user()->employee;

        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee.');

        $overtimeRequests = OvertimeRequest::with(['attendance'])
            ->where('employee_id', $employee->id)
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->latest('attendance_date')
            ->paginate(15);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $overtimeRequests]);
    }

    public function store(StoreOvertimeRequestRequest $request)
    {
        $employee = $request->user()->employee;

        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee.');

        try {
            $overtimeRequest = $this->service->submit($employee, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Overtime request berhasil diajukan',
                'data' => $overtimeRequest,
            ], 201);
        } catch (OvertimeRequestValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function cancel(Request $request, OvertimeRequest $overtimeRequest)
    {
        $employee = $request->user()->employee;

        abort_if(
            ! $employee || $overtimeRequest->employee_id !== $employee->id,
            403,
            'Anda tidak berhak membatalkan overtime request ini.'
        );

        try {
            $overtimeRequest = $this->service->cancel($overtimeRequest);

            return response()->json([
                'success' => true,
                'message' => 'Overtime request berhasil dibatalkan',
                'data' => $overtimeRequest,
            ]);
        } catch (OvertimeRequestValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function claim(Request $request, OvertimeRequest $overtimeRequest)
    {
        $employee = $request->user()->employee;

        abort_if(
            ! $employee || $overtimeRequest->employee_id !== $employee->id,
            403,
            'Anda tidak berhak melakukan claim untuk overtime request ini.'
        );

        try {
            $overtimeRequest = $this->service->claim($overtimeRequest);

            return response()->json([
                'success' => true,
                'message' => 'Overtime berhasil di-claim',
                'data' => $overtimeRequest,
            ]);
        } catch (OvertimeRequestValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }
}