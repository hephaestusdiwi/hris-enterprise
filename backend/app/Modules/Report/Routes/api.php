<?php

use App\Modules\Report\Controllers\FinanceReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view finance reports')
    ->get('/reports/finance/spending', [FinanceReportController::class, 'spending']);

Route::middleware('permission:export finance reports')
    ->get('/reports/finance/spending/export', [FinanceReportController::class, 'exportSpending']);

Route::middleware('permission:view finance reports')
    ->get('/reports/finance/outstanding', [FinanceReportController::class, 'outstanding']);

Route::middleware('permission:export finance reports')
    ->get('/reports/finance/outstanding/export', [FinanceReportController::class, 'exportOutstanding']);