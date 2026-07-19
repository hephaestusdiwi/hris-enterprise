<?php

use App\Modules\Employee\Controllers\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view employees')->group(function () {
    Route::get('/employees/next-number', [EmployeeController::class, 'nextNumber']);
    Route::get('/employees/org-chart', [EmployeeController::class, 'orgChart']);
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::get('/employees/{employee}', [EmployeeController::class, 'show']);
});

Route::middleware('permission:create employees')->post('/employees', [EmployeeController::class, 'store']);
Route::middleware('permission:edit employees')->put('/employees/{employee}', [EmployeeController::class, 'update']);
Route::middleware('permission:delete employees')->delete('/employees/{employee}', [EmployeeController::class, 'destroy']);