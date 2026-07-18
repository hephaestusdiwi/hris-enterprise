<?php

use App\Modules\EmploymentStatus\Controllers\EmploymentStatusController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view employment statuses')->group(function () {
    Route::get('/employment-statuses', [EmploymentStatusController::class, 'index']);
    Route::get('/employment-statuses/{employment_status}', [EmploymentStatusController::class, 'show']);
});

Route::middleware('permission:create employment statuses')->post('/employment-statuses', [EmploymentStatusController::class, 'store']);
Route::middleware('permission:edit employment statuses')->put('/employment-statuses/{employment_status}', [EmploymentStatusController::class, 'update']);
Route::middleware('permission:delete employment statuses')->delete('/employment-statuses/{employment_status}', [EmploymentStatusController::class, 'destroy']);