<?php

use App\Modules\Attendance\Controllers\AttendanceDeviceApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('attendance.device')->prefix('device')->group(function () {
    Route::get('/attendance/today', [AttendanceDeviceApiController::class, 'today']);
    Route::post('/attendance/clock-in', [AttendanceDeviceApiController::class, 'clockIn']);
    Route::post('/attendance/clock-out', [AttendanceDeviceApiController::class, 'clockOut']);
});