<?php

use App\Modules\Payroll\Controllers\CompanyBankSettingController;
use App\Modules\Payroll\Controllers\CompanyPayrollAttendanceSettingController;
use App\Modules\Payroll\Controllers\PayrollApprovalController;
use App\Modules\Payroll\Controllers\PayrollDisbursementController;
use App\Modules\Payroll\Controllers\PayrollReportController;
use App\Modules\Payroll\Controllers\PayrollRunController;
use App\Modules\Payroll\Controllers\PayslipController;
use Illuminate\Support\Facades\Route;

Route::get('/my-payslips', [PayslipController::class, 'myPayslips']);
Route::get('/my-payslips/{payslip}', [PayslipController::class, 'myPayslipShow']);

Route::middleware('permission:view payroll runs')->group(function () {
    Route::get('/payroll-runs', [PayrollRunController::class, 'index']);
    Route::get('/payroll-runs/{payrollRun}', [PayrollRunController::class, 'show']);
    Route::get('/payslips/{payslip}', [PayslipController::class, 'show']);
    Route::get('/payroll-attendance-setting', [CompanyPayrollAttendanceSettingController::class, 'show']);

    // Payroll Reports STEP 1 — Salary Reports. Reuse permission 'view payroll
    // runs' yang sudah ada (laporan ini murni membaca data payroll yang
    // sudah digating izin itu) — sengaja tidak bikin permission baru.
    Route::get('/payroll-reports/salary/detail', [PayrollReportController::class, 'salaryDetail']);
    Route::get('/payroll-reports/salary/summary', [PayrollReportController::class, 'salarySummary']);
    Route::get('/payroll-reports/salary/detail/export/excel', [PayrollReportController::class, 'exportSalaryDetailExcel']);
    Route::get('/payroll-reports/salary/summary/export/excel', [PayrollReportController::class, 'exportSalarySummaryExcel']);
    Route::get('/payroll-reports/salary/detail/export/pdf', [PayrollReportController::class, 'exportSalaryDetailPdf']);
    Route::get('/payroll-reports/salary/summary/export/pdf', [PayrollReportController::class, 'exportSalarySummaryPdf']);
});

Route::middleware('permission:create payroll runs')->group(function () {
    Route::post('/payroll-runs', [PayrollRunController::class, 'store']);
    Route::put('/payroll-runs/{payrollRun}/participants', [PayrollRunController::class, 'updateParticipants']);
    Route::post('/payroll-runs/{payrollRun}/proceed-payslip', [PayrollRunController::class, 'proceedPayslip']);
    Route::post('/payroll-runs/{payrollRun}/cancel', [PayrollRunController::class, 'cancel']);
});

Route::middleware('permission:request payroll approval')
    ->post('/payroll-runs/{payrollRun}/request-approval', [PayrollRunController::class, 'requestApproval']);

Route::middleware('permission:lock payroll runs')->post('/payroll-runs/{payrollRun}/lock', [PayrollRunController::class, 'lock']);

Route::middleware('permission:publish payroll runs')->group(function () {
    Route::post('/payroll-runs/{payrollRun}/publish', [PayrollRunController::class, 'publish']);
    Route::post('/payroll-runs/{payrollRun}/unpublish', [PayrollRunController::class, 'unpublish']);
    Route::post('/payslips/{payslip}/publish', [PayslipController::class, 'publish']);
    Route::post('/payslips/{payslip}/unpublish', [PayslipController::class, 'unpublish']);
});

Route::middleware('permission:edit payroll settings')->group(function () {
    Route::put('/payroll-attendance-setting', [CompanyPayrollAttendanceSettingController::class, 'update']);
    Route::get('/payroll-bank-setting', [CompanyBankSettingController::class, 'show']);
    Route::put('/payroll-bank-setting', [CompanyBankSettingController::class, 'update']);
});

Route::middleware('permission:manage payroll disbursements')->group(function () {
    Route::get('/payroll-runs/{payrollRun}/disbursements', [PayrollDisbursementController::class, 'index']);
    Route::post('/payroll-runs/{payrollRun}/disbursements', [PayrollDisbursementController::class, 'store']);
    Route::get('/disbursements/{disbursement}/download', [PayrollDisbursementController::class, 'download']);
    Route::post('/disbursements/{disbursement}/mark-sent', [PayrollDisbursementController::class, 'markSent']);
    Route::post('/disbursements/{disbursement}/mark-confirmed', [PayrollDisbursementController::class, 'markConfirmed']);
    Route::post('/disbursements/{disbursement}/mark-failed', [PayrollDisbursementController::class, 'markFailed']);
});

Route::get('/payroll-approvals', [PayrollApprovalController::class, 'index']);
Route::post('/payroll-approvals/{decision}/decide', [PayrollApprovalController::class, 'decide']);