<?php

namespace App\Modules\ChangeShiftRequest\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ChangeShiftRequest\Exceptions\ChangeShiftRequestValidationException;
use App\Modules\ChangeShiftRequest\Models\ChangeShiftRequest;
use App\Modules\ChangeShiftRequest\Requests\StoreChangeShiftRequestRequest;
use App\Modules\ChangeShiftRequest\Services\ChangeShiftRequestService;
use Illuminate\Http\Request;

class ChangeShiftRequestController extends Controller
{
    public function __construct(private ChangeShiftRequestService $service)
    {
    }

    public function index(Request $request)
    {
        $changeShiftRequests = ChangeShiftRequest::with(['employee', 'currentShift', 'requestedShift'])
            ->when($request->query('employee_id'), fn ($q, $v) => $q->where('employee_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('date_from'), fn ($q, $v) => $q->where('attendance_date', '>=', $v))
            ->when($request->query('date_to'), fn ($q, $v) => $q->where('attendance_date', '<=', $v))
            ->latest('attendance_date')
            ->paginate(15);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $changeShiftRequests]);
    }

    public function myRequests(Request $request)
    {
        $employee = $request->user()->employee;

        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee.');

        $changeShiftRequests = ChangeShiftRequest::with(['currentShift', 'requestedShift'])
            ->where('employee_id', $employee->id)
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->latest('attendance_date')
            ->paginate(15);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $changeShiftRequests]);
    }

    public function store(StoreChangeShiftRequestRequest $request)
    {
        $employee = $request->user()->employee;

        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee.');

        try {
            $changeShiftRequest = $this->service->submit($employee, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Change shift request berhasil diajukan',
                'data' => $changeShiftRequest->load(['currentShift', 'requestedShift']),
            ], 201);
        } catch (ChangeShiftRequestValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function cancel(Request $request, ChangeShiftRequest $changeShiftRequest)
    {
        $employee = $request->user()->employee;

        abort_if(
            ! $employee || $changeShiftRequest->employee_id !== $employee->id,
            403,
            'Anda tidak berhak membatalkan change shift request ini.'
        );

        try {
            $changeShiftRequest = $this->service->cancel($changeShiftRequest);

            return response()->json([
                'success' => true,
                'message' => 'Change shift request berhasil dibatalkan',
                'data' => $changeShiftRequest,
            ]);
        } catch (ChangeShiftRequestValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }
}