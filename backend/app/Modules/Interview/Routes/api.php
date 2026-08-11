<?php

use App\Modules\Interview\Controllers\InterviewController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('interviews')->group(function () {
    Route::get('/', [InterviewController::class, 'index']);
    Route::post('/', [InterviewController::class, 'store']);
    Route::get('/{interview}', [InterviewController::class, 'show']);
    Route::post('/{interview}/start', [InterviewController::class, 'start']);
    Route::post('/{interview}/complete', [InterviewController::class, 'complete']);
    Route::post('/{interview}/cancel', [InterviewController::class, 'cancel']);
});