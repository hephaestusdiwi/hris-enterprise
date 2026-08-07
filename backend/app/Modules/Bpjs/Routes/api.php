<?php

use App\Modules\Bpjs\Controllers\BpjsCompanyRegistrationController;
use App\Modules\Bpjs\Controllers\BpjsJkkRiskClassRateController;
use App\Modules\Bpjs\Controllers\BpjsRateConfigController;
use App\Modules\Bpjs\Controllers\CompanyBpjsSettingController;
use App\Modules\Bpjs\Controllers\EmployeeBpjsParticipationController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view bpjs settings')->group(function () {
    Route::get('/bpjs/rate-configs', [BpjsRateConfigController::class, 'index']);
    Route::get('/bpjs/jkk-risk-class-rates', [BpjsJkkRiskClassRateController::class, 'index']);
    Route::get('/bpjs/company-registrations', [BpjsCompanyRegistrationController::class, 'index']);
    Route::get('/bpjs/company-setting', [CompanyBpjsSettingController::class, 'show']);
    Route::get('/bpjs/employee-participations', [EmployeeBpjsParticipationController::class, 'index']);
    Route::get('/bpjs/employee-participations/{employee}', [EmployeeBpjsParticipationController::class, 'show']);
});

Route::middleware('permission:create bpjs settings')->group(function () {
    Route::post('/bpjs/rate-configs', [BpjsRateConfigController::class, 'store']);
    Route::post('/bpjs/jkk-risk-class-rates', [BpjsJkkRiskClassRateController::class, 'store']);
    Route::post('/bpjs/company-registrations', [BpjsCompanyRegistrationController::class, 'store']);
});

Route::middleware('permission:edit bpjs settings')->group(function () {
    Route::put('/bpjs/company-setting', [CompanyBpjsSettingController::class, 'update']);
    Route::put('/bpjs/employee-participations/{employee}', [EmployeeBpjsParticipationController::class, 'update']);
});

Route::middleware('permission:delete bpjs settings')->group(function () {
    Route::delete('/bpjs/rate-configs/{bpjsRateConfig}', [BpjsRateConfigController::class, 'destroy']);
    Route::delete('/bpjs/company-registrations/{bpjsCompanyRegistration}', [BpjsCompanyRegistrationController::class, 'destroy']);
});