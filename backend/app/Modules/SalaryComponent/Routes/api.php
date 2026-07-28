<?php

use App\Modules\SalaryComponent\Controllers\SalaryComponentController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view salary components')->group(function () {
    Route::get('/salary-components', [SalaryComponentController::class, 'index']);
    Route::get('/salary-components/{salaryComponent}', [SalaryComponentController::class, 'show']);
});

Route::middleware('permission:create salary components')->post('/salary-components', [SalaryComponentController::class, 'store']);
Route::middleware('permission:edit salary components')->put('/salary-components/{salaryComponent}', [SalaryComponentController::class, 'update']);
Route::middleware('permission:delete salary components')->delete('/salary-components/{salaryComponent}', [SalaryComponentController::class, 'destroy']);