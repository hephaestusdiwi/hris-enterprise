<?php

use App\Modules\Payroll\Controllers\AnnualTaxReconciliationController;
use App\Modules\Payroll\Controllers\CompanyBankSettingController;
use App\Modules\Payroll\Controllers\CompanyPayrollAttendanceSettingController;
use App\Modules\Payroll\Controllers\EmployeeNonRegularInputController;
use App\Modules\Payroll\Controllers\NonRegularPayrollComponentController;
use App\Modules\Payroll\Controllers\PayrollApprovalController;
use App\Modules\Payroll\Controllers\PayrollBpjsReportController;
use App\Modules\Payroll\Controllers\PayrollDisbursementController;
use App\Modules\Payroll\Controllers\PayrollNonRegularReportController;
use App\Modules\Payroll\Controllers\PayrollReportController;
use App\Modules\Payroll\Controllers\PayrollRunController;
use App\Modules\Payroll\Controllers\PayrollTaxReportController;
use App\Modules\Payroll\Controllers\PayrollThrReportController;
use App\Modules\Payroll\Controllers\PayslipController;
use App\Modules\Payroll\Controllers\ThrPolicyController;
use Illuminate\Support\Facades\Route;

Route::get('/my-payslips', [PayslipController::class, 'myPayslips']);
Route::get('/my-payslips/{payslip}', [PayslipController::class, 'myPayslipShow']);
Route::get('/my-payslips/{payslip}/download', [PayslipController::class, 'myPayslipDownload']);

Route::middleware('permission:view payroll runs')->group(function () {
    Route::get('/payroll-runs', [PayrollRunController::class, 'index']);
    Route::get('/payroll-runs/{payrollRun}', [PayrollRunController::class, 'show']);
    Route::get('/payslips/{payslip}', [PayslipController::class, 'show']);
    Route::get('/payslips/{payslip}/download', [PayslipController::class, 'downloadPdf']);
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

    // Payroll Reports STEP 2 — BPJS Reports. Sama-sama reuse permission
    // 'view payroll runs', konsisten sama STEP 1.
    Route::get('/payroll-reports/bpjs/detail', [PayrollBpjsReportController::class, 'bpjsDetail']);
    Route::get('/payroll-reports/bpjs/summary', [PayrollBpjsReportController::class, 'bpjsSummary']);
    Route::get('/payroll-reports/bpjs/detail/export/excel', [PayrollBpjsReportController::class, 'exportBpjsDetailExcel']);
    Route::get('/payroll-reports/bpjs/summary/export/excel', [PayrollBpjsReportController::class, 'exportBpjsSummaryExcel']);
    Route::get('/payroll-reports/bpjs/detail/export/pdf', [PayrollBpjsReportController::class, 'exportBpjsDetailPdf']);
    Route::get('/payroll-reports/bpjs/summary/export/pdf', [PayrollBpjsReportController::class, 'exportBpjsSummaryPdf']);

    // Payroll Reports STEP 3 — Tax Reports (PPh21). Reuse permission 'view
    // payroll runs' juga, konsisten sama STEP 1 & 2.
    Route::get('/payroll-reports/tax/detail', [PayrollTaxReportController::class, 'taxDetail']);
    Route::get('/payroll-reports/tax/summary', [PayrollTaxReportController::class, 'taxSummary']);
    Route::get('/payroll-reports/tax/detail/export/excel', [PayrollTaxReportController::class, 'exportTaxDetailExcel']);
    Route::get('/payroll-reports/tax/summary/export/excel', [PayrollTaxReportController::class, 'exportTaxSummaryExcel']);
    Route::get('/payroll-reports/tax/detail/export/pdf', [PayrollTaxReportController::class, 'exportTaxDetailPdf']);
    Route::get('/payroll-reports/tax/summary/export/pdf', [PayrollTaxReportController::class, 'exportTaxSummaryPdf']);

    // Fase 8 — Tax Compliance & Government Reporting. Reuse permission
    // 'view payroll runs' juga, konsisten sama report lain di atas.
    Route::get('/payroll-reports/annual-tax-recap', [AnnualTaxReconciliationController::class, 'recap']);
    Route::get('/payroll-reports/annual-tax-recap/export/excel', [AnnualTaxReconciliationController::class, 'exportRecapExcel']);
    Route::get('/employees/{employee}/bpa1', [AnnualTaxReconciliationController::class, 'downloadBpa1']);
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

// ==================== FASE 7 — THR & NON-REGULAR PAYROLL ====================
// Component master data & THR Policy diperlakukan sebagai "payroll settings"
// (reuse permission generik existing), TIDAK bikin permission baru buat ini —
// permission baru cuma buat yang eksplisit diminta section K (view/create/
// edit/request approval/lock/publish thr|non regular payroll).
Route::middleware('permission:view thr payroll')->group(function () {
    Route::get('/thr-policies', [ThrPolicyController::class, 'index']);
    Route::get('/thr-policies/eligible-employees', [ThrPolicyController::class, 'eligibleEmployees']);
    Route::get('/thr-policies/{thrPolicy}', [ThrPolicyController::class, 'show']);
    Route::get('/payroll-reports/thr/detail', [PayrollThrReportController::class, 'detail']);
    Route::get('/payroll-reports/thr/detail/export/excel', [PayrollThrReportController::class, 'exportExcel']);
    Route::get('/payroll-reports/thr/detail/export/pdf', [PayrollThrReportController::class, 'exportPdf']);
});

Route::middleware('permission:edit thr payroll')->group(function () {
    Route::post('/thr-policies', [ThrPolicyController::class, 'store']);
    Route::put('/thr-policies/{thrPolicy}', [ThrPolicyController::class, 'update']);
    Route::delete('/thr-policies/{thrPolicy}', [ThrPolicyController::class, 'destroy']);
});

Route::middleware('permission:view non regular payroll')->group(function () {
    Route::get('/non-regular-payroll-components', [NonRegularPayrollComponentController::class, 'index']);
    Route::get('/non-regular-payroll-components/{nonRegularPayrollComponent}', [NonRegularPayrollComponentController::class, 'show']);
    Route::get('/employee-non-regular-inputs', [EmployeeNonRegularInputController::class, 'index']);
    Route::get('/employee-non-regular-inputs/{employeeNonRegularInput}', [EmployeeNonRegularInputController::class, 'show']);
    Route::get('/payroll-reports/non-regular/detail', [PayrollNonRegularReportController::class, 'detail']);
    Route::get('/payroll-reports/non-regular/detail/export/excel', [PayrollNonRegularReportController::class, 'exportExcel']);
    Route::get('/payroll-reports/non-regular/detail/export/pdf', [PayrollNonRegularReportController::class, 'exportPdf']);
});

Route::middleware('permission:create non regular payroll')->group(function () {
    Route::post('/non-regular-payroll-components', [NonRegularPayrollComponentController::class, 'store']);
    Route::post('/employee-non-regular-inputs', [EmployeeNonRegularInputController::class, 'store']);
});

Route::middleware('permission:edit non regular payroll')->group(function () {
    Route::put('/non-regular-payroll-components/{nonRegularPayrollComponent}', [NonRegularPayrollComponentController::class, 'update']);
    Route::delete('/non-regular-payroll-components/{nonRegularPayrollComponent}', [NonRegularPayrollComponentController::class, 'destroy']);
    Route::put('/employee-non-regular-inputs/{employeeNonRegularInput}', [EmployeeNonRegularInputController::class, 'update']);
    Route::post('/employee-non-regular-inputs/{employeeNonRegularInput}/void', [EmployeeNonRegularInputController::class, 'void']);
    Route::post('/employee-non-regular-inputs/{employeeNonRegularInput}/mark-ready', [EmployeeNonRegularInputController::class, 'markReady']);
    Route::post('/employee-non-regular-inputs/bulk-mark-ready', [EmployeeNonRegularInputController::class, 'bulkMarkReady']);
});