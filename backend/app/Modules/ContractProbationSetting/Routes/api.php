<?php

use App\Modules\ContractProbationSetting\Controllers\ContractProbationSettingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('permission:view employees')->get('/contract-probation-settings', [ContractProbationSettingController::class, 'show']);
    Route::middleware('permission:edit contract probation settings')->put('/contract-probation-settings', [ContractProbationSettingController::class, 'update']);
});
