<?php

use App\Modules\Attendance\Controllers\AttendanceController;
use App\Modules\Attendance\Controllers\AttendanceSelfServiceController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view attendances')->group(function () {
    Route::get('/attendances', [AttendanceController::class, 'index']);
    Route::get('/attendances/{attendance}', [AttendanceController::class, 'show']);
});

Route::middleware('permission:create attendances')->post('/attendances', [AttendanceController::class, 'store']);
Route::middleware('permission:edit attendances')->put('/attendances/{attendance}', [AttendanceController::class, 'update']);
Route::middleware('permission:delete attendances')->delete('/attendances/{attendance}', [AttendanceController::class, 'destroy']);

// Self-service (setiap user login dengan employee terhubung boleh akses, tanpa permission khusus)
Route::post('/attendance/clock-in', [AttendanceSelfServiceController::class, 'clockIn']);
Route::post('/attendance/clock-out', [AttendanceSelfServiceController::class, 'clockOut']);
Route::get('/attendance/today', [AttendanceSelfServiceController::class, 'today']);