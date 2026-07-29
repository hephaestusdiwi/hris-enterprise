<?php

use App\Modules\SalaryStructure\Controllers\SalaryStructureController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view salary structures')->group(function () {
    Route::get('/salary-structures', [SalaryStructureController::class, 'index']);
    Route::get('/salary-structures/{salaryStructure}', [SalaryStructureController::class, 'show']);
});

Route::middleware('permission:create salary structures')->post('/salary-structures', [SalaryStructureController::class, 'store']);
Route::middleware('permission:delete salary structures')->delete('/salary-structures/{salaryStructure}', [SalaryStructureController::class, 'destroy']);