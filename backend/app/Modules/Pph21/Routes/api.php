<?php

use App\Modules\Pph21\Controllers\CompanyTaxSettingController;
use App\Modules\Pph21\Controllers\EmployeeTaxProfileController;
use App\Modules\Pph21\Controllers\PtkpConfigController;
use App\Modules\Pph21\Controllers\TaxBracketConfigController;
use App\Modules\Pph21\Controllers\TerRateBracketController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view tax settings')->group(function () {
    Route::get('/pph21/ptkp-configs', [PtkpConfigController::class, 'index']);
    Route::get('/pph21/ter-rate-brackets', [TerRateBracketController::class, 'index']);
    Route::get('/pph21/tax-bracket-configs', [TaxBracketConfigController::class, 'index']);
    Route::get('/pph21/company-setting', [CompanyTaxSettingController::class, 'show']);
    Route::get('/pph21/employee-tax-profiles', [EmployeeTaxProfileController::class, 'index']);
    Route::get('/pph21/employee-tax-profiles/{employee}', [EmployeeTaxProfileController::class, 'show']);
});

Route::middleware('permission:create tax settings')->group(function () {
    Route::post('/pph21/ptkp-configs', [PtkpConfigController::class, 'store']);
    Route::post('/pph21/ter-rate-brackets', [TerRateBracketController::class, 'store']);
    Route::post('/pph21/tax-bracket-configs', [TaxBracketConfigController::class, 'store']);
});

Route::middleware('permission:edit tax settings')->group(function () {
    Route::put('/pph21/company-setting', [CompanyTaxSettingController::class, 'update']);
    Route::put('/pph21/employee-tax-profiles/{employee}', [EmployeeTaxProfileController::class, 'update']);
    Route::post('/pph21/employee-tax-profiles/{employee}/ptkp-status', [EmployeeTaxProfileController::class, 'storePtkpStatus']);
});

Route::middleware('permission:delete tax settings')->group(function () {
    Route::delete('/pph21/ter-rate-brackets/{terRateBracket}', [TerRateBracketController::class, 'destroy']);
    Route::delete('/pph21/tax-bracket-configs/{taxBracketConfig}', [TaxBracketConfigController::class, 'destroy']);
});