<?php

namespace App\Modules\LeaveBalance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\LeaveBalance\Models\LeaveBalance;
use Illuminate\Http\Request;

class LeaveBalanceController extends Controller
{
    public function index(Request $request)
    {
        $balances = LeaveBalance::with(['employee', 'leaveType', 'adjustments.createdBy'])
            ->when($request->query('employee_id'), fn ($q, $v) => $q->where('employee_id', $v))
            ->when($request->query('leave_type_id'), fn ($q, $v) => $q->where('leave_type_id', $v))
            ->when($request->query('year'), fn ($q, $v) => $q->whereYear('period_start', $v))
            ->latest()
            ->paginate(20);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $balances]);
    }

    public function show(LeaveBalance $leaveBalance)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $leaveBalance->load(['employee', 'leaveType', 'adjustments.createdBy']),
        ]);
    }
}