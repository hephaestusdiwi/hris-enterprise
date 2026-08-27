<?php

use App\Modules\OvertimeRequest\Controllers\OvertimeRequestApprovalController;
use App\Modules\OvertimeRequest\Controllers\OvertimeRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:create overtime requests')->post('/overtime-requests', [OvertimeRequestController::class, 'store']);
Route::middleware('permission:view overtime requests')->get('/overtime-requests', [OvertimeRequestController::class, 'index']);

// Self-service -- sama pola dengan /my-attendance-requests, tanpa permission
// khusus, ownership check di controller.
Route::get('/my-overtime-requests', [OvertimeRequestController::class, 'myRequests']);
Route::post('/overtime-requests/{overtimeRequest}/cancel', [OvertimeRequestController::class, 'cancel']);
Route::post('/overtime-requests/{overtimeRequest}/claim', [OvertimeRequestController::class, 'claim']);

// Approval -- sama pola dengan attendance-request-approvals, eligibility
// approver di-cek di dalam service (ApprovalStepApproverResolver), bukan
// lewat permission middleware terpisah.
Route::get('/overtime-request-approvals', [OvertimeRequestApprovalController::class, 'index']);
Route::post('/overtime-request-approvals/{decision}/decide', [OvertimeRequestApprovalController::class, 'decide']);