<?php

use App\Modules\Expense\Controllers\ExpenseCategoryController;
use App\Modules\Expense\Controllers\ExpensePolicyAssignmentController;
use App\Modules\Expense\Controllers\ExpensePolicyController;
use App\Modules\Expense\Controllers\ExpenseSubcategoryController;
use Illuminate\Support\Facades\Route;

// ---- Expense Category ----
Route::middleware('permission:view expense categories')->group(function () {
    Route::get('/expense-categories', [ExpenseCategoryController::class, 'index']);
    Route::get('/expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'show']);
});
Route::middleware('permission:create expense categories')->post('/expense-categories', [ExpenseCategoryController::class, 'store']);
Route::middleware('permission:edit expense categories')->put('/expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'update']);
Route::middleware('permission:delete expense categories')->delete('/expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'destroy']);

// ---- Expense Subcategory ----
Route::middleware('permission:view expense subcategories')->group(function () {
    Route::get('/expense-subcategories', [ExpenseSubcategoryController::class, 'index']);
    Route::get('/expense-subcategories/{expenseSubcategory}', [ExpenseSubcategoryController::class, 'show']);
});
Route::middleware('permission:create expense subcategories')->post('/expense-subcategories', [ExpenseSubcategoryController::class, 'store']);
Route::middleware('permission:edit expense subcategories')->put('/expense-subcategories/{expenseSubcategory}', [ExpenseSubcategoryController::class, 'update']);
Route::middleware('permission:delete expense subcategories')->delete('/expense-subcategories/{expenseSubcategory}', [ExpenseSubcategoryController::class, 'destroy']);

// ---- Expense Policy ----
// Tidak ada destroy() -- konsisten dengan CashAdvancePolicy/ReimbursementPolicy,
// policy cuma dinonaktifkan lewat is_active via update(), tidak pernah dihapus.
Route::middleware('permission:view expense policies')->group(function () {
    Route::get('/expense-policies', [ExpensePolicyController::class, 'index']);
    Route::get('/expense-policies/{expensePolicy}', [ExpensePolicyController::class, 'show']);
});
Route::middleware('permission:create expense policies')->post('/expense-policies', [ExpensePolicyController::class, 'store']);
Route::middleware('permission:edit expense policies')->put('/expense-policies/{expensePolicy}', [ExpensePolicyController::class, 'update']);

// ---- Expense Policy Assignment ----
// Permission direuse dari expense policies (view/create/edit) -- tidak
// ada permission baru, sesuai pola "manage reimbursement policies" yang
// juga menggabungkan Policy+Balance jadi satu permission. Tidak ada
// destroy() dan tidak ada endpoint resolve publik (GET /employees/{id}/
// expense-policy) di STEP ini -- resolver cuma dipakai internal, ditunda
// sampai ada consumer (Expense Claim) beneran.
Route::middleware('permission:view expense policies')->group(function () {
    Route::get('/expense-policy-assignments', [ExpensePolicyAssignmentController::class, 'index']);
    Route::get('/expense-policy-assignments/{expensePolicyAssignment}', [ExpensePolicyAssignmentController::class, 'show']);
});
Route::middleware('permission:create expense policies')->post('/expense-policy-assignments', [ExpensePolicyAssignmentController::class, 'store']);
Route::middleware('permission:edit expense policies')->put('/expense-policy-assignments/{expensePolicyAssignment}', [ExpensePolicyAssignmentController::class, 'update']);