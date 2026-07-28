<?php

use App\Modules\LeaveType\Controllers\LeaveTypeController;
use Illuminate\Support\Facades\Route;

Route::get('/leave-types/self-service', [LeaveTypeController::class, 'activeForSelfService']);

Route::middleware('permission:view leave types')->group(function () {
    Route::get('/leave-types', [LeaveTypeController::class, 'index']);
    Route::get('/leave-types/{leaveType}', [LeaveTypeController::class, 'show']);
});

Route::middleware('permission:create leave types')->post('/leave-types', [LeaveTypeController::class, 'store']);
Route::middleware('permission:edit leave types')->put('/leave-types/{leaveType}', [LeaveTypeController::class, 'update']);
Route::middleware('permission:delete leave types')->delete('/leave-types/{leaveType}', [LeaveTypeController::class, 'destroy']);