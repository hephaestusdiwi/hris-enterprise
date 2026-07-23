<?php

use App\Modules\Attendance\Controllers\AttendanceDeviceApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('attendance.device')->prefix('device')->group(function () {
    Route::get('/attendance/today', [AttendanceDeviceApiController::class, 'today']);
    Route::post('/attendance/clock-in', [AttendanceDeviceApiController::class, 'clockIn']);
    Route::post('/attendance/clock-out', [AttendanceDeviceApiController::class, 'clockOut']);

    Route::post('/attendance/face/today', [AttendanceDeviceApiController::class, 'todayByFace']);
    Route::post('/attendance/face/clock-in', [AttendanceDeviceApiController::class, 'clockInByFace']);
    Route::post('/attendance/face/clock-out', [AttendanceDeviceApiController::class, 'clockOutByFace']);

    Route::post('/attendance/qr/today', [AttendanceDeviceApiController::class, 'todayByQr']);
    Route::post('/attendance/qr/clock-in', [AttendanceDeviceApiController::class, 'clockInByQr']);
    Route::post('/attendance/qr/clock-out', [AttendanceDeviceApiController::class, 'clockOutByQr']);

    Route::get('/attendance/office-qr', [AttendanceDeviceApiController::class, 'officeQr']);
});