<?php

use App\Modules\AttendanceSetting\Controllers\AttendanceSettingController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view attendance settings')->group(function () {
    Route::get('/attendance-settings', [AttendanceSettingController::class, 'index']);
    Route::get('/attendance-settings/{attendance_setting}', [AttendanceSettingController::class, 'show']);
});

Route::middleware('permission:create attendance settings')->post('/attendance-settings', [AttendanceSettingController::class, 'store']);
Route::middleware('permission:edit attendance settings')->put('/attendance-settings/{attendance_setting}', [AttendanceSettingController::class, 'update']);
Route::middleware('permission:delete attendance settings')->delete('/attendance-settings/{attendance_setting}', [AttendanceSettingController::class, 'destroy']);