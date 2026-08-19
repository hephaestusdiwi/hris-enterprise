<?php

namespace App\Modules\CashAdvance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CashAdvance\Models\CashAdvanceCategory;
use App\Modules\CashAdvance\Requests\StoreCashAdvanceCategoryRequest;
use App\Modules\CashAdvance\Requests\UpdateCashAdvanceCategoryRequest;

class CashAdvanceCategoryController extends Controller
{
    public function index()
    {
        $categories = CashAdvanceCategory::orderBy('name')->get();

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $categories]);
    }

    public function store(StoreCashAdvanceCategoryRequest $request)
    {
        $category = CashAdvanceCategory::create($request->validated());

        return response()->json(['success' => true, 'message' => 'Kategori berhasil dibuat', 'data' => $category], 201);
    }

    public function update(UpdateCashAdvanceCategoryRequest $request, CashAdvanceCategory $cashAdvanceCategory)
    {
        $cashAdvanceCategory->update($request->validated());

        return response()->json(['success' => true, 'message' => 'Kategori berhasil diperbarui', 'data' => $cashAdvanceCategory->fresh()]);
    }

    public function destroy(CashAdvanceCategory $cashAdvanceCategory)
    {
        if ($cashAdvanceCategory->requestItems()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori ini sudah pernah dipakai di request, tidak bisa dihapus. Nonaktifkan saja.',
                'data' => null,
            ], 422);
        }

        $cashAdvanceCategory->delete();

        return response()->json(['success' => true, 'message' => 'Kategori berhasil dihapus', 'data' => null]);
    }
}