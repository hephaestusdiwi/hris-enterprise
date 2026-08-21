<?php

use App\Modules\Expense\Controllers\ExpenseCategoryController;
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