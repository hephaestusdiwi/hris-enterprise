<?php

namespace App\Modules\Expense\Controllers;

use App\Modules\Expense\Models\ExpenseSubcategory;
use App\Http\Controllers\Controller;
use App\Modules\Expense\Requests\StoreExpenseSubcategoryRequest;
use App\Modules\Expense\Requests\UpdateExpenseSubcategoryRequest;
use Illuminate\Http\Request;

class ExpenseSubcategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = ExpenseSubcategory::with('category.company')->latest();

        if ($request->filled('expense_category_id')) {
            $query->where('expense_category_id', $request->integer('expense_category_id'));
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $query->paginate(15),
        ]);
    }

    public function store(StoreExpenseSubcategoryRequest $request)
    {
        $subcategory = ExpenseSubcategory::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Expense Subcategory berhasil dibuat',
            'data' => $subcategory->load('category.company'),
        ], 201);
    }

    public function show(ExpenseSubcategory $expenseSubcategory)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $expenseSubcategory->load('category.company'),
        ]);
    }

    public function update(UpdateExpenseSubcategoryRequest $request, ExpenseSubcategory $expenseSubcategory)
    {
        $expenseSubcategory->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Expense Subcategory berhasil diperbarui',
            'data' => $expenseSubcategory->load('category.company'),
        ]);
    }

    public function destroy(ExpenseSubcategory $expenseSubcategory)
    {
        // Tidak ada dependency child di STEP 1 (ExpenseItem belum ada),
        // jadi cukup soft delete langsung -- sama seperti Department/LeaveType.
        $expenseSubcategory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Expense Subcategory berhasil dihapus',
            'data' => null,
        ]);
    }
}