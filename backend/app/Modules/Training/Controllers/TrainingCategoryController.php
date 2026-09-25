<?php

namespace App\Modules\Training\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Training\Models\TrainingCategory;
use App\Modules\Training\Requests\StoreTrainingCategoryRequest;
use App\Modules\Training\Requests\UpdateTrainingCategoryRequest;

class TrainingCategoryController extends Controller
{
    public function index()
    {
        $categories = TrainingCategory::with('company')->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $categories,
        ]);
    }

    public function store(StoreTrainingCategoryRequest $request)
    {
        $category = TrainingCategory::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Training Category berhasil dibuat',
            'data' => $category->load('company'),
        ], 201);
    }

    public function show(TrainingCategory $trainingCategory)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $trainingCategory->load('company'),
        ]);
    }

    public function update(UpdateTrainingCategoryRequest $request, TrainingCategory $trainingCategory)
    {
        $trainingCategory->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Training Category berhasil diperbarui',
            'data' => $trainingCategory->load('company'),
        ]);
    }

    public function destroy(TrainingCategory $trainingCategory)
    {
        // Pola sama seperti ExpenseCategoryController::destroy() -- jangan
        // hapus kategori yang masih dipakai program, suruh nonaktifkan saja.
        if ($trainingCategory->programs()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori ini masih dipakai oleh Training Program, tidak bisa dihapus. Nonaktifkan saja.',
                'data' => null,
            ], 422);
        }

        $trainingCategory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Training Category berhasil dihapus',
            'data' => null,
        ]);
    }
}