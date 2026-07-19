<?php

use App\Modules\JobLevel\Controllers\JobLevelController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view job levels')->group(function () {
    Route::get('/job-levels', [JobLevelController::class, 'index']);
    Route::get('/job-levels/{job_level}', [JobLevelController::class, 'show']);
});

Route::middleware('permission:create job levels')->post('/job-levels', [JobLevelController::class, 'store']);
Route::middleware('permission:edit job levels')->put('/job-levels/{job_level}', [JobLevelController::class, 'update']);
Route::middleware('permission:delete job levels')->delete('/job-levels/{job_level}', [JobLevelController::class, 'destroy']);