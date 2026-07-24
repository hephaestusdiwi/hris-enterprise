<?php

use App\Modules\WorkingSchedule\Controllers\SchedulerController;
use App\Modules\WorkingSchedule\Controllers\WorkingScheduleAssignmentController;
use App\Modules\WorkingSchedule\Controllers\WorkingScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view working schedules')->group(function () {
    Route::get('/working-schedules', [WorkingScheduleController::class, 'index']);
    Route::get('/working-schedules/{working_schedule}', [WorkingScheduleController::class, 'show']);
});

Route::middleware('permission:create working schedules')->post('/working-schedules', [WorkingScheduleController::class, 'store']);
Route::middleware('permission:edit working schedules')->put('/working-schedules/{working_schedule}', [WorkingScheduleController::class, 'update']);
Route::middleware('permission:delete working schedules')->delete('/working-schedules/{working_schedule}', [WorkingScheduleController::class, 'destroy']);

Route::middleware('permission:view working schedules')->group(function () {
    Route::get('/working-schedule-assignments', [WorkingScheduleAssignmentController::class, 'index']);
    Route::get('/working-schedule-assignments/{workingScheduleAssignment}', [WorkingScheduleAssignmentController::class, 'show']);
});

Route::middleware('permission:create working schedules')->post('/working-schedule-assignments', [WorkingScheduleAssignmentController::class, 'store']);
Route::middleware('permission:edit working schedules')->put('/working-schedule-assignments/{workingScheduleAssignment}', [WorkingScheduleAssignmentController::class, 'update']);
Route::middleware('permission:delete working schedules')->delete('/working-schedule-assignments/{workingScheduleAssignment}', [WorkingScheduleAssignmentController::class, 'destroy']);

/*
|--------------------------------------------------------------------------
| Scheduler
|--------------------------------------------------------------------------
*/

Route::middleware('permission:view working schedules')
    ->get('/scheduler/employees', [SchedulerController::class, 'index']);

Route::middleware('permission:edit working schedules')->group(function () {
    Route::post('/scheduler/assign', [SchedulerController::class, 'assign']);
    Route::post('/scheduler/bulk-assign', [SchedulerController::class, 'bulkAssign']);
    Route::delete('/scheduler/scheduled-changes/{scheduledChange}', [SchedulerController::class, 'cancelScheduledChange']);
});