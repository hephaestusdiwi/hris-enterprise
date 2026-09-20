<?php // backend/app/Modules/Grooming/Routes/api.php

use App\Modules\Grooming\Controllers\GroomingSelfController;
use App\Modules\Grooming\Controllers\GroomingStandardController;
use App\Modules\Grooming\Controllers\GroomingStoreController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('grooming-standards')->group(function () {
        Route::get('/', [GroomingStandardController::class, 'index']);
        Route::get('/{groomingStandard}', [GroomingStandardController::class, 'show']);
        Route::post('/{type}', [GroomingStandardController::class, 'store'])->where('type', 'self|store');
        Route::put('/{groomingStandard}', [GroomingStandardController::class, 'update']);
        Route::post('/{groomingStandard}/new-version', [GroomingStandardController::class, 'createVersion']);
        Route::post('/{groomingStandard}/activate', [GroomingStandardController::class, 'activate']);
        Route::post('/{groomingStandard}/archive', [GroomingStandardController::class, 'archive']);
    });

    Route::prefix('grooming-self')->group(function () {
        Route::get('/active-standard', [GroomingSelfController::class, 'activeStandard']);
        Route::get('/my-history', [GroomingSelfController::class, 'myHistory']);
        Route::get('/monitoring-summary', [GroomingSelfController::class, 'monitoringSummary']);
        Route::get('/', [GroomingSelfController::class, 'index']);
        Route::post('/', [GroomingSelfController::class, 'store']);
        Route::get('/{groomingSelfSubmission}', [GroomingSelfController::class, 'show']);
    });

    Route::prefix('grooming-store')->group(function () {
        Route::get('/active-standard', [GroomingStoreController::class, 'activeStandard']);
        Route::get('/accessible-branches', [GroomingStoreController::class, 'accessibleBranches']);
        Route::get('/monitoring-summary', [GroomingStoreController::class, 'monitoringSummary']);
        Route::get('/', [GroomingStoreController::class, 'index']);
        Route::post('/', [GroomingStoreController::class, 'store']);
        Route::get('/{groomingStoreSubmission}', [GroomingStoreController::class, 'show']);
    });
});