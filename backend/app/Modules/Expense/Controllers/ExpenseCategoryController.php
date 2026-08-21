<?php

namespace App\Modules\Expense\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Expense\Models\ExpenseCategory;
use App\Modules\Expense\Requests\StoreExpenseCategoryRequest;
use App\Modules\Expense\Requests\UpdateExpenseCategoryRequest;

class ExpenseCategoryController extends Controller
{
    public function index()
    {
        $categories = ExpenseCategory::with('company')->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $categories,
        ]);
    }

    public function store(StoreExpenseCategoryRequest $request)
    {
        $category = ExpenseCategory::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Expense Category berhasil dibuat',
            'data' => $category->load('company'),
        ], 201);
    }

    public function show(ExpenseCategory $expenseCategory)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $expenseCategory->load('company'),
        ]);
    }

    public function update(UpdateExpenseCategoryRequest $request, ExpenseCategory $expenseCategory)
    {
        $expenseCategory->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Expense Category berhasil diperbarui',
            'data' => $expenseCategory->load('company'),
        ]);
    }

    public function destroy(ExpenseCategory $expenseCategory)
    {
        // Pola sama seperti CashAdvanceCategoryController::destroy() --
        // jangan hard/soft-delete kategori yang masih punya subcategory,
        // suruh nonaktifkan saja (is_active=false).
        if ($expenseCategory->subcategories()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori ini masih memiliki subcategory, tidak bisa dihapus. Nonaktifkan saja.',
                'data' => null,
            ], 422);
        }

        $expenseCategory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Expense Category berhasil dihapus',
            'data' => null,
        ]);
    }
}