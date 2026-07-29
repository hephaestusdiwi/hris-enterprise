<?php

use App\Modules\Holiday\Controllers\HolidayController;
use App\Modules\Holiday\Controllers\HolidayImportController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:create holidays')->group(function () {
    Route::get('/holidays/import/national/preview', [HolidayImportController::class, 'preview']);
    Route::post('/holidays/import/national', [HolidayImportController::class, 'import']);
});

Route::middleware('permission:view holidays')->group(function () {
    Route::get('/holidays', [HolidayController::class, 'index']);
    Route::get('/holidays/calendar', [HolidayController::class, 'calendar']);
    Route::get('/holidays/{holiday}', [HolidayController::class, 'show']);
});

Route::middleware('permission:create holidays')->post('/holidays', [HolidayController::class, 'store']);
Route::middleware('permission:edit holidays')->put('/holidays/{holiday}', [HolidayController::class, 'update']);
Route::middleware('permission:delete holidays')->delete('/holidays/{holiday}', [HolidayController::class, 'destroy']);