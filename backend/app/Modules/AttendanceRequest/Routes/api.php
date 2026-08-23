<?php

use App\Modules\AttendanceRequest\Controllers\AttendanceRequestApprovalController;
use App\Modules\AttendanceRequest\Controllers\AttendanceRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:create attendance requests')->post('/attendance-requests', [AttendanceRequestController::class, 'store']);
Route::middleware('permission:view attendance requests')->get('/attendance-requests', [AttendanceRequestController::class, 'index']);

// Self-service: employee lihat & batalkan request miliknya sendiri. Tanpa
// permission khusus, sama seperti /my-leave-requests dan /attendance/today.
Route::get('/my-attendance-requests', [AttendanceRequestController::class, 'myRequests']);
Route::post('/attendance-requests/{attendanceRequest}/cancel', [AttendanceRequestController::class, 'cancel']);

// Approval: tanpa permission middleware, otorisasi object-level lewat
// ApprovalStepApproverResolver di service (sama seperti /leave-approvals
// dan /attendance-approvals).
Route::get('/attendance-request-approvals', [AttendanceRequestApprovalController::class, 'index']);
Route::post('/attendance-request-approvals/{decision}/decide', [AttendanceRequestApprovalController::class, 'decide']);