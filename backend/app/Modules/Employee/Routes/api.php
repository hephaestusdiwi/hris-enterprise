<?php

use App\Modules\Employee\Controllers\EmployeeController;
use App\Modules\Employee\Controllers\EmployeeFaceController;
use App\Modules\Employee\Controllers\EmployeePhotoController;
use App\Modules\Employee\Controllers\EmployeeQrController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view employees')->group(function () {
    Route::get('/employees/next-number', [EmployeeController::class, 'nextNumber']);
    Route::get('/employees/org-chart', [EmployeeController::class, 'orgChart']);
    Route::get('/employees/available-users', [EmployeeController::class, 'availableUsers']);
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::get('/employees/{employee}', [EmployeeController::class, 'show']);
    Route::get('/employees/{employee}/hierarchy', [EmployeeController::class, 'hierarchy']);
});

Route::middleware('permission:create employees')->post('/employees', [EmployeeController::class, 'store']);
Route::middleware('permission:edit employees')->put('/employees/{employee}', [EmployeeController::class, 'update']);
Route::middleware('permission:delete employees')->delete('/employees/{employee}', [EmployeeController::class, 'destroy']);

Route::middleware('permission:edit employees')->group(function () {
    Route::post('/employees/{employee}/face/enroll', [EmployeeFaceController::class, 'enroll']);
    Route::delete('/employees/{employee}/face', [EmployeeFaceController::class, 'destroyEnrollment']);
});

Route::middleware('permission:edit employees')->group(function () {
    Route::post('/employees/{employee}/qr/generate', [EmployeeQrController::class, 'generate']);
    Route::delete('/employees/{employee}/qr', [EmployeeQrController::class, 'destroy']);
});

Route::middleware('permission:edit employees')->group(function () {
    Route::post('/employees/{employee}/photo', [EmployeePhotoController::class, 'upload']);
    Route::delete('/employees/{employee}/photo', [EmployeePhotoController::class, 'destroy']);
});

Route::middleware('permission:edit employees')->post('/employees/{employee}/resend-invite', [EmployeeController::class, 'resendInvite']);