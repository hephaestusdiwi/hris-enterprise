<?php

use App\Modules\Holiday\Controllers\HolidayController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view holidays')->group(function () {
    Route::get('/holidays', [HolidayController::class, 'index']);
    Route::get('/holidays/{holiday}', [HolidayController::class, 'show']);
});

Route::middleware('permission:create holidays')->post('/holidays', [HolidayController::class, 'store']);
Route::middleware('permission:edit holidays')->put('/holidays/{holiday}', [HolidayController::class, 'update']);
Route::middleware('permission:delete holidays')->delete('/holidays/{holiday}', [HolidayController::class, 'destroy']);