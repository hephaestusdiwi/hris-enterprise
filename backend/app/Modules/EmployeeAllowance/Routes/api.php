<?php

use App\Modules\EmployeeAllowance\Controllers\EmployeeAllowanceController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view employee allowances')->group(function () {
    Route::get('/employee-allowances', [EmployeeAllowanceController::class, 'index']);
    Route::get('/employee-allowances/summary', [EmployeeAllowanceController::class, 'summary']);
    Route::get('/employee-allowances/export', [EmployeeAllowanceController::class, 'export']);
    Route::get('/employee-allowances/{employeeAllowance}', [EmployeeAllowanceController::class, 'show']);
});

Route::middleware('permission:create employee allowances')->group(function () {
    Route::post('/employee-allowances', [EmployeeAllowanceController::class, 'store']);
    Route::post('/employee-allowances/import', [EmployeeAllowanceController::class, 'import']);
});

Route::middleware('permission:edit employee allowances')->group(function () {
    Route::put('/employee-allowances/{employeeAllowance}', [EmployeeAllowanceController::class, 'update']);
    Route::post('/employee-allowances/{employeeAllowance}/void', [EmployeeAllowanceController::class, 'void']);
    Route::post('/employee-allowances/bulk-mark-ready', [EmployeeAllowanceController::class, 'bulkMarkReady']);
    Route::post('/employee-allowances/bulk-void', [EmployeeAllowanceController::class, 'bulkVoid']);
});