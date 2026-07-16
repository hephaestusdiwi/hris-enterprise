<?php

use App\Http\Controllers\Auth\AuthController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);

    Route::middleware('permission:view users')->get('/users', function () {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => User::select('id', 'name', 'email')->get(),
        ]);
    });
});
