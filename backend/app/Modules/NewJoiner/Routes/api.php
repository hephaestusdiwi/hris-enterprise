<?php

use App\Modules\NewJoiner\Controllers\NewJoinerController;
use App\Modules\NewJoiner\Controllers\NewJoinerPublicController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('new-joiners')->group(function () {
    Route::get('/', [NewJoinerController::class, 'index']);
    Route::post('/', [NewJoinerController::class, 'store']);
    Route::get('/{newJoiner}', [NewJoinerController::class, 'show']);
    Route::post('/{newJoiner}/proceed-as-employee', [NewJoinerController::class, 'proceedAsEmployee']);
    Route::post('/{newJoiner}/convert-to-employee', [NewJoinerController::class, 'convertToEmployee']);
});

Route::middleware('throttle:30,1')->prefix('new-joiner-form')->group(function () {
    Route::get('/{token}', [NewJoinerPublicController::class, 'show']);
    Route::post('/{token}', [NewJoinerPublicController::class, 'store']);
});