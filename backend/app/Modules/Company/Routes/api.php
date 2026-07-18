<?php

use App\Modules\Company\Controllers\CompanyController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view companies')->group(function () {
    Route::get('/companies', [CompanyController::class, 'index']);
    Route::get('/companies/{company}', [CompanyController::class, 'show']);
});

Route::middleware('permission:create companies')->post('/companies', [CompanyController::class, 'store']);
Route::middleware('permission:edit companies')->put('/companies/{company}', [CompanyController::class, 'update']);
Route::middleware('permission:delete companies')->delete('/companies/{company}', [CompanyController::class, 'destroy']);
