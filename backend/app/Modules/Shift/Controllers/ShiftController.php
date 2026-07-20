<?php

namespace App\Modules\Shift\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shift\Models\Shift;
use App\Modules\Shift\Requests\StoreShiftRequest;
use App\Modules\Shift\Requests\UpdateShiftRequest;

class ShiftController extends Controller
{
    public function index()
    {
        $shifts = Shift::with('company')->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $shifts,
        ]);
    }

    public function store(StoreShiftRequest $request)
    {
        $shift = Shift::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Shift berhasil dibuat',
            'data' => $shift->load('company'),
        ], 201);
    }

    public function show(Shift $shift)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $shift->load('company'),
        ]);
    }

    public function update(UpdateShiftRequest $request, Shift $shift)
    {
        $shift->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Shift berhasil diperbarui',
            'data' => $shift->load('company'),
        ]);
    }

    public function destroy(Shift $shift)
    {
        $shift->delete();

        return response()->json([
            'success' => true,
            'message' => 'Shift berhasil dihapus',
            'data' => null,
        ]);
    }
}