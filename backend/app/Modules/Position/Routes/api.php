<?php

use App\Modules\Position\Controllers\PositionController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view positions')->group(function () {
    Route::get('/positions', [PositionController::class, 'index']);
    Route::get('/positions/{position}', [PositionController::class, 'show']);
});

Route::middleware('permission:create positions')->post('/positions', [PositionController::class, 'store']);
Route::middleware('permission:edit positions')->put('/positions/{position}', [PositionController::class, 'update']);
Route::middleware('permission:delete positions')->delete('/positions/{position}', [PositionController::class, 'destroy']);