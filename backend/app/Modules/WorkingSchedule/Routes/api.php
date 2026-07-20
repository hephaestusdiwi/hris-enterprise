<?php

use App\Modules\WorkingSchedule\Controllers\WorkingScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view working schedules')->group(function () {
    Route::get('/working-schedules', [WorkingScheduleController::class, 'index']);
    Route::get('/working-schedules/{working_schedule}', [WorkingScheduleController::class, 'show']);
});

Route::middleware('permission:create working schedules')->post('/working-schedules', [WorkingScheduleController::class, 'store']);
Route::middleware('permission:edit working schedules')->put('/working-schedules/{working_schedule}', [WorkingScheduleController::class, 'update']);
Route::middleware('permission:delete working schedules')->delete('/working-schedules/{working_schedule}', [WorkingScheduleController::class, 'destroy']);