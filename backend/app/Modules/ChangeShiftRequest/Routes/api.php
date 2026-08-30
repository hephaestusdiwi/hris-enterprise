<?php

use App\Modules\ChangeShiftRequest\Controllers\ChangeShiftRequestApprovalController;
use App\Modules\ChangeShiftRequest\Controllers\ChangeShiftRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:create change shift requests')->post('/change-shift-requests', [ChangeShiftRequestController::class, 'store']);
Route::middleware('permission:view change shift requests')->get('/change-shift-requests', [ChangeShiftRequestController::class, 'index']);

Route::get('/my-change-shift-requests', [ChangeShiftRequestController::class, 'myRequests']);
Route::post('/change-shift-requests/{changeShiftRequest}/cancel', [ChangeShiftRequestController::class, 'cancel']);

Route::get('/change-shift-request-approvals', [ChangeShiftRequestApprovalController::class, 'index']);
Route::post('/change-shift-request-approvals/{decision}/decide', [ChangeShiftRequestApprovalController::class, 'decide']);