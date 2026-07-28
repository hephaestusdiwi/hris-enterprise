<?php

use App\Modules\LeaveRequest\Controllers\LeaveApprovalController;
use App\Modules\LeaveRequest\Controllers\LeaveCalendarController;
use App\Modules\LeaveRequest\Controllers\LeaveRequestController;
use Illuminate\Support\Facades\Route;

Route::post('/leave-requests/attachments', [LeaveRequestController::class, 'uploadAttachment']);
Route::post('/leave-requests', [LeaveRequestController::class, 'store']);
Route::get('/my-leave-requests', [LeaveRequestController::class, 'myRequests']);
Route::post('/leave-requests/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel']);

Route::middleware('permission:view leave requests')->get('/leave-requests', [LeaveRequestController::class, 'index']);
Route::get('/leave-calendar/summary', [LeaveCalendarController::class, 'summary']);
Route::get('/leave-calendar', [LeaveCalendarController::class, 'index']);
Route::get('/leave-calendar/{leaveRequest}', [LeaveCalendarController::class, 'show']);

Route::get('/leave-approvals', [LeaveApprovalController::class, 'index']);
Route::post('/leave-approvals/{decision}/decide', [LeaveApprovalController::class, 'decide']);