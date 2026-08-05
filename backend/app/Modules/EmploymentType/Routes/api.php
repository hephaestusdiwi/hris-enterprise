<?php

use App\Modules\EmploymentType\Controllers\EmploymentTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view employment types')->group(function () {
    Route::get('/employment-types', [
        EmploymentTypeController::class,
        'index',
    ]);

    Route::get('/employment-types/{employment_type}', [
        EmploymentTypeController::class,
        'show',
    ]);
});

Route::middleware('permission:create employment types')
    ->post('/employment-types', [
        EmploymentTypeController::class,
        'store',
    ]);

Route::middleware('permission:edit employment types')
    ->put('/employment-types/{employment_type}', [
        EmploymentTypeController::class,
        'update',
    ]);

Route::middleware('permission:delete employment types')
    ->delete('/employment-types/{employment_type}', [
        EmploymentTypeController::class,
        'destroy',
    ]);