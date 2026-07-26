<?php

namespace App\Modules\LeaveBalance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\LeaveBalance\Models\LeaveBalance;
use App\Modules\LeaveBalance\Requests\StoreLeaveBalanceAdjustmentRequest;
use Illuminate\Http\Request;

class LeaveBalanceAdjustmentController extends Controller
{
    public function store(StoreLeaveBalanceAdjustmentRequest $request, LeaveBalance $leaveBalance)
    {
        $adjustment = $leaveBalance->adjustments()->create([
            ...$request->validated(),
            'created_by_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Adjustment berhasil ditambahkan',
            'data' => $leaveBalance->fresh()->load('adjustments'),
        ], 201);
    }
}