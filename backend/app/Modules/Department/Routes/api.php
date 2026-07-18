<?php

use App\Modules\Department\Controllers\DepartmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view departments')->group(function () {
    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::get('/departments/{department}', [DepartmentController::class, 'show']);
});

Route::middleware('permission:create departments')->post('/departments', [DepartmentController::class, 'store']);
Route::middleware('permission:edit departments')->put('/departments/{department}', [DepartmentController::class, 'update']);
Route::middleware('permission:delete departments')->delete('/departments/{department}', [DepartmentController::class, 'destroy']);