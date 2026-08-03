<?php

use App\Modules\EmployeeDeduction\Controllers\EmployeeDeductionController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view employee deductions')->group(function () {
    Route::get('/employee-deductions', [EmployeeDeductionController::class, 'index']);
    Route::get('/employee-deductions/summary', [EmployeeDeductionController::class, 'summary']);
    Route::get('/employee-deductions/export', [EmployeeDeductionController::class, 'export']);
    Route::get('/employee-deductions/{employeeDeduction}', [EmployeeDeductionController::class, 'show']);
});

Route::middleware('permission:create employee deductions')->group(function () {
    Route::post('/employee-deductions', [EmployeeDeductionController::class, 'store']);
    Route::post('/employee-deductions/import', [EmployeeDeductionController::class, 'import']);
});

Route::middleware('permission:edit employee deductions')->group(function () {
    Route::put('/employee-deductions/{employeeDeduction}', [EmployeeDeductionController::class, 'update']);
    Route::post('/employee-deductions/{employeeDeduction}/void', [EmployeeDeductionController::class, 'void']);
    Route::post('/employee-deductions/bulk-mark-ready', [EmployeeDeductionController::class, 'bulkMarkReady']);
    Route::post('/employee-deductions/bulk-void', [EmployeeDeductionController::class, 'bulkVoid']);
});