<?php

use App\Modules\CashAdvance\Controllers\CashAdvanceApprovalController;
use App\Modules\CashAdvance\Controllers\CashAdvanceAttachmentController;
use App\Modules\CashAdvance\Controllers\CashAdvanceCategoryController;
use App\Modules\CashAdvance\Controllers\CashAdvanceController;
use App\Modules\CashAdvance\Controllers\CashAdvancePolicyController;
use App\Modules\CashAdvance\Controllers\CashAdvanceSettlementController;
use Illuminate\Support\Facades\Route;

// ---- Policy ----
Route::middleware('permission:view cash advance policies')->get('/cash-advance-policies', [CashAdvancePolicyController::class, 'index']);
Route::middleware('permission:create cash advance policies')->post('/cash-advance-policies', [CashAdvancePolicyController::class, 'store']);
Route::middleware('permission:edit cash advance policies')->put('/cash-advance-policies/{cashAdvancePolicy}', [CashAdvancePolicyController::class, 'update']);

// ---- Category ----
Route::middleware('permission:view cash advance categories')->get('/cash-advance-categories', [CashAdvanceCategoryController::class, 'index']);
Route::middleware('permission:create cash advance categories')->post('/cash-advance-categories', [CashAdvanceCategoryController::class, 'store']);
Route::middleware('permission:edit cash advance categories')->put('/cash-advance-categories/{cashAdvanceCategory}', [CashAdvanceCategoryController::class, 'update']);
Route::middleware('permission:delete cash advance categories')->delete('/cash-advance-categories/{cashAdvanceCategory}', [CashAdvanceCategoryController::class, 'destroy']);

// ---- Employee self-service ----
Route::get('/my-cash-advances', [CashAdvanceController::class, 'myCashAdvances']);
Route::get('/my-cash-advances/{cashAdvance}', [CashAdvanceController::class, 'myCashAdvanceShow']);
Route::middleware('permission:create cash advances')->post('/cash-advances', [CashAdvanceController::class, 'store']);
Route::post('/cash-advances/{cashAdvance}/cancel', [CashAdvanceController::class, 'cancel']);

// ---- Management ----
Route::middleware('permission:view cash advances')->group(function () {
    Route::get('/cash-advances', [CashAdvanceController::class, 'index']);
    Route::get('/cash-advances/{cashAdvance}', [CashAdvanceController::class, 'show']);
});
Route::middleware('permission:disburse cash advances')->post('/cash-advances/{cashAdvance}/disburse', [CashAdvanceController::class, 'disburse']);

// ---- Approval (request level) ----
Route::get('/cash-advance-approvals', [CashAdvanceApprovalController::class, 'index']);
Route::post('/cash-advance-approvals/{decision}/decide', [CashAdvanceApprovalController::class, 'decide']);

// ---- Settlement ----
Route::post('/cash-advances/{cashAdvance}/settlement', [CashAdvanceSettlementController::class, 'store']);
Route::get('/cash-advances/{cashAdvance}/settlement', [CashAdvanceSettlementController::class, 'show']);
Route::get('/cash-advance-settlement-approvals', [CashAdvanceSettlementController::class, 'index']);
Route::middleware('permission:verify cash advance settlements')->post('/cash-advance-settlements/{settlement}/decide', [CashAdvanceSettlementController::class, 'decide']);

// ---- Attachments ----
Route::post('/cash-advances/{cashAdvance}/attachments', [CashAdvanceAttachmentController::class, 'store']);
Route::delete('/cash-advances/{cashAdvance}/attachments/{attachment}', [CashAdvanceAttachmentController::class, 'destroy']);