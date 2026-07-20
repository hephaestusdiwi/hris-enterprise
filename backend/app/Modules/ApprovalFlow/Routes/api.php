<?php

use App\Modules\ApprovalFlow\Controllers\ApprovalFlowAssignmentController;
use App\Modules\ApprovalFlow\Controllers\ApprovalFlowController;
use App\Modules\ApprovalFlow\Controllers\ApprovalStepController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view approval flows')->group(function () {
    Route::get('/approval-flows', [ApprovalFlowController::class, 'index']);
    Route::get('/approval-flows/roles', [ApprovalFlowController::class, 'roles']);
    Route::get('/approval-flows/{approvalFlow}', [ApprovalFlowController::class, 'show']);
    Route::get('/approval-flows/{approvalFlow}/steps', [ApprovalStepController::class, 'index']);
    Route::get('/approval-flows/{approvalFlow}/steps/{step}', [ApprovalStepController::class, 'show']);
    Route::get('/approval-flows/{approvalFlow}/assignments', [ApprovalFlowAssignmentController::class, 'index']);
    Route::get('/approval-flow-assignments/{assignment}', [ApprovalFlowAssignmentController::class, 'show']);
});

Route::middleware('permission:create approval flows')->group(function () {
    Route::post('/approval-flows', [ApprovalFlowController::class, 'store']);
    Route::post('/approval-flows/{approvalFlow}/steps', [ApprovalStepController::class, 'store']);
    Route::post('/approval-flows/{approvalFlow}/assignments', [ApprovalFlowAssignmentController::class, 'store']);
});

Route::middleware('permission:edit approval flows')->group(function () {
    Route::put('/approval-flows/{approvalFlow}', [ApprovalFlowController::class, 'update']);
    Route::put('/approval-flows/{approvalFlow}/steps/{step}', [ApprovalStepController::class, 'update']);
    Route::put('/approval-flow-assignments/{assignment}', [ApprovalFlowAssignmentController::class, 'update']);
});

Route::middleware('permission:delete approval flows')->group(function () {
    Route::delete('/approval-flows/{approvalFlow}', [ApprovalFlowController::class, 'destroy']);
    Route::delete('/approval-flows/{approvalFlow}/steps/{step}', [ApprovalStepController::class, 'destroy']);
    Route::delete('/approval-flow-assignments/{assignment}', [ApprovalFlowAssignmentController::class, 'destroy']);
});