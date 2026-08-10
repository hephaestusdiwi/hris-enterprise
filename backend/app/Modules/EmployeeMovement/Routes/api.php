<?php

use App\Modules\EmployeeMovement\Controllers\EmployeeMovementApprovalController;
use App\Modules\EmployeeMovement\Controllers\EmployeeMovementController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('permission:view employee movements')->get('/employee-movements', [EmployeeMovementController::class, 'index']);
    Route::middleware('permission:create employee movements')->post('/employees/{employee}/movements', [EmployeeMovementController::class, 'store']);
    Route::get('/employee-movements/approvals/pending', [EmployeeMovementApprovalController::class, 'pending']);
    Route::post('/employee-movements/approvals/{decision}/decide', [EmployeeMovementApprovalController::class, 'decide']);
    Route::get('/employee-movements/{employeeMovement}', [EmployeeMovementController::class, 'show']);
    Route::post('/employee-movements/{employeeMovement}/cancel', [EmployeeMovementController::class, 'cancel']);
});
