<?php

use App\Modules\Screening\Controllers\ScreeningController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('screenings')->group(function () {
    Route::get('/', [ScreeningController::class, 'index']);
    Route::post('/', [ScreeningController::class, 'store']);
    Route::get('/{screening}', [ScreeningController::class, 'show']);
    Route::post('/{screening}/decide', [ScreeningController::class, 'decide']);
});