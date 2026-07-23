<?php

use App\Modules\Attendance\Controllers\AttendanceController;
use App\Modules\Attendance\Controllers\AttendanceDeviceController;
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

// Attendance Device (admin management)
Route::middleware('permission:view attendance devices')->group(function () {
    Route::get('/attendance-devices', [AttendanceDeviceController::class, 'index']);
    Route::get('/attendance-devices/{attendanceDevice}', [AttendanceDeviceController::class, 'show']);
    Route::get('/attendance-devices/{attendanceDevice}/office-qr', [AttendanceDeviceController::class, 'officeQr']);
});

Route::middleware('permission:create attendance devices')->post('/attendance-devices', [AttendanceDeviceController::class, 'store']);

Route::middleware('permission:edit attendance devices')->group(function () {
    Route::put('/attendance-devices/{attendanceDevice}', [AttendanceDeviceController::class, 'update']);
    Route::post('/attendance-devices/{attendanceDevice}/regenerate-token', [AttendanceDeviceController::class, 'regenerateToken']);
});

Route::middleware('permission:delete attendance devices')->delete('/attendance-devices/{attendanceDevice}', [AttendanceDeviceController::class, 'destroy']);