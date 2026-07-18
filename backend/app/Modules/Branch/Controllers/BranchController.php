<?php

namespace App\Modules\Branch\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Branch\Models\Branch;
use App\Modules\Branch\Requests\StoreBranchRequest;
use App\Modules\Branch\Requests\UpdateBranchRequest;

class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::with('company')->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $branches,
        ]);
    }

    public function store(StoreBranchRequest $request)
    {
        $branch = Branch::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Branch berhasil dibuat',
            'data' => $branch->load('company'),
        ], 201);
    }

    public function show(Branch $branch)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $branch->load('company'),
        ]);
    }

    public function update(UpdateBranchRequest $request, Branch $branch)
    {
        $branch->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Branch berhasil diperbarui',
            'data' => $branch->load('company'),
        ]);
    }

    public function destroy(Branch $branch)
    {
        $branch->delete();

        return response()->json([
            'success' => true,
            'message' => 'Branch berhasil dihapus',
            'data' => null,
        ]);
    }
}