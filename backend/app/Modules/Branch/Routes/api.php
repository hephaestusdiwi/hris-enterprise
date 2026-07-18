<?php

use App\Modules\Branch\Controllers\BranchController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view branches')->group(function () {
    Route::get('/branches', [BranchController::class, 'index']);
    Route::get('/branches/{branch}', [BranchController::class, 'show']);
});

Route::middleware('permission:create branches')->post('/branches', [BranchController::class, 'store']);
Route::middleware('permission:edit branches')->put('/branches/{branch}', [BranchController::class, 'update']);
Route::middleware('permission:delete branches')->delete('/branches/{branch}', [BranchController::class, 'destroy']);