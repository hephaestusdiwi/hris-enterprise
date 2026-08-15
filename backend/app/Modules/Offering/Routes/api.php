<?php

use App\Modules\Offering\Controllers\OfferingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('offerings')->group(function () {
    Route::get('/', [OfferingController::class, 'index']);
    Route::post('/', [OfferingController::class, 'store']);
    Route::get('/{offering}', [OfferingController::class, 'show']);
    Route::put('/{offering}', [OfferingController::class, 'update']);
    Route::post('/{offering}/send', [OfferingController::class, 'send']);
    Route::post('/{offering}/respond', [OfferingController::class, 'respond']);
    Route::post('/{offering}/withdraw', [OfferingController::class, 'withdraw']);
});