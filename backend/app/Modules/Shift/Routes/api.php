<?php

use App\Modules\Shift\Controllers\ShiftController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view shifts')->group(function () {
    Route::get('/shifts', [ShiftController::class, 'index']);
    Route::get('/shifts/{shift}', [ShiftController::class, 'show']);
});

Route::middleware('permission:create shifts')->post('/shifts', [ShiftController::class, 'store']);
Route::middleware('permission:edit shifts')->put('/shifts/{shift}', [ShiftController::class, 'update']);
Route::middleware('permission:delete shifts')->delete('/shifts/{shift}', [ShiftController::class, 'destroy']);