<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);

    Route::middleware('permission:view dashboard')->get('/dashboard', [DashboardController::class, 'index']);

    Route::middleware('permission:view users')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/roles', [UserController::class, 'roles']);
        Route::get('/users/{user}', [UserController::class, 'show']);
    });

    Route::middleware('permission:create users')->post('/users', [UserController::class, 'store']);
    Route::middleware('permission:edit users')->put('/users/{user}', [UserController::class, 'update']);
    Route::middleware('permission:delete users')->delete('/users/{user}', [UserController::class, 'destroy']);

    require __DIR__.'/../app/Modules/Company/Routes/api.php';
    require __DIR__.'/../app/Modules/Branch/Routes/api.php';
});