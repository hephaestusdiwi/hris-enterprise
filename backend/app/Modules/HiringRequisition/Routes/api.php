<?php

use App\Modules\HiringRequisition\Controllers\HiringRequisitionApprovalController;
use App\Modules\HiringRequisition\Controllers\HiringRequisitionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('hiring-requisitions')->group(function () {
    Route::get('/', [HiringRequisitionController::class, 'index']);
    Route::post('/', [HiringRequisitionController::class, 'store']);
    Route::get('/approvals/pending', [HiringRequisitionApprovalController::class, 'pending']);
    Route::post('/approvals/{decision}/decide', [HiringRequisitionApprovalController::class, 'decide']);
    Route::get('/{hiringRequisition}', [HiringRequisitionController::class, 'show']);
    Route::post('/{hiringRequisition}/cancel', [HiringRequisitionController::class, 'cancel']);
});