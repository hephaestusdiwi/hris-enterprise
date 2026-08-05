<?php

use App\Modules\Loan\Controllers\LoanApprovalController;
use App\Modules\Loan\Controllers\LoanController;
use Illuminate\Support\Facades\Route;

// Self-service (employee lihat loan miliknya sendiri)
Route::get('/my-loans', [LoanController::class, 'myLoans']);
Route::get('/my-loans/{loan}', [LoanController::class, 'myLoanShow']);

// Kalkulator, tidak menyimpan apa pun
Route::post('/loans/preview', [LoanController::class, 'preview']);

Route::middleware('permission:view loans')->group(function () {
    Route::get('/loans', [LoanController::class, 'index']);
    Route::get('/loans/{loan}', [LoanController::class, 'show']);
});

Route::middleware('permission:create loans')->post('/loans', [LoanController::class, 'store']);

Route::middleware('permission:edit loans')->group(function () {
    Route::put('/loans/{loan}', [LoanController::class, 'update']);
    Route::post('/loans/{loan}/submit', [LoanController::class, 'submit']);
});

Route::middleware('permission:disburse loans')->post('/loans/{loan}/disburse', [LoanController::class, 'disburse']);
Route::middleware('permission:cancel loans')->post('/loans/{loan}/cancel', [LoanController::class, 'cancel']);

Route::get('/loan-approvals', [LoanApprovalController::class, 'index']);
Route::post('/loan-approvals/{decision}/decide', [LoanApprovalController::class, 'decide']);