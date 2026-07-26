<?php

use App\Modules\LeaveBalance\Controllers\LeaveBalanceAdjustmentController;
use App\Modules\LeaveBalance\Controllers\LeaveBalanceController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view leave balances')->group(function () {
    Route::get('/leave-balances', [LeaveBalanceController::class, 'index']);
    Route::get('/leave-balances/{leaveBalance}', [LeaveBalanceController::class, 'show']);
});

Route::middleware('permission:edit leave balances')->post('/leave-balances/{leaveBalance}/adjustments', [LeaveBalanceAdjustmentController::class, 'store']);