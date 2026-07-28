<?php

namespace App\Modules\LeaveType\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\LeaveType\Models\LeaveType;
use App\Modules\LeaveType\Requests\StoreLeaveTypeRequest;
use App\Modules\LeaveType\Requests\UpdateLeaveTypeRequest;

class LeaveTypeController extends Controller
{
    public function index()
    {
        $leaveTypes = LeaveType::with('company')->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $leaveTypes,
        ]);
    }

    public function store(StoreLeaveTypeRequest $request)
    {
        $leaveType = LeaveType::create($this->normalizeCarryOver($request->validated()));

        return response()->json([
            'success' => true,
            'message' => 'Leave Type berhasil dibuat',
            'data' => $leaveType->load('company'),
        ], 201);
    }

    public function show(LeaveType $leaveType)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $leaveType->load('company'),
        ]);
    }

    public function update(UpdateLeaveTypeRequest $request, LeaveType $leaveType)
    {
        $leaveType->update($this->normalizeCarryOver($request->validated()));

        return response()->json([
            'success' => true,
            'message' => 'Leave Type berhasil diperbarui',
            'data' => $leaveType->load('company'),
        ]);
    }

    public function destroy(LeaveType $leaveType)
    {
        $leaveType->delete();

        return response()->json([
            'success' => true,
            'message' => 'Leave Type berhasil dihapus',
            'data' => null,
        ]);
    }
    
    public function activeForSelfService()
    {
        $leaveTypes = LeaveType::where('company_id', request()->user()->employee?->company_id)
            ->where('is_active', true)
            ->get(['id', 'name', 'color', 'allow_half_day', 'allow_hourly', 'requires_attachment']);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $leaveTypes]);
    }

    private function normalizeCarryOver(array $data): array
    {
        if (empty($data['carry_over_allowed'])) {
            $data['carry_over_max_days'] = null;
            $data['carry_over_expiry_month'] = null;
        }

        return $data;
    }
}