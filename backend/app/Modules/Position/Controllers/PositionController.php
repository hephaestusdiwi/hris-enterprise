<?php

namespace App\Modules\Position\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Position\Models\Position;
use App\Modules\Position\Requests\StorePositionRequest;
use App\Modules\Position\Requests\UpdatePositionRequest;

class PositionController extends Controller
{
    public function index()
    {
        $positions = Position::with(['company', 'parent'])->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $positions,
        ]);
    }

    public function store(StorePositionRequest $request)
    {
        $position = Position::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Position berhasil dibuat',
            'data' => $position->load(['company', 'parent']),
        ], 201);
    }

    public function show(Position $position)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $position->load(['company', 'parent']),
        ]);
    }

    public function update(UpdatePositionRequest $request, Position $position)
    {
        $position->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Position berhasil diperbarui',
            'data' => $position->load(['company', 'parent']),
        ]);
    }

    public function destroy(Position $position)
    {
        $position->delete();

        return response()->json([
            'success' => true,
            'message' => 'Position berhasil dihapus',
            'data' => null,
        ]);
    }
}