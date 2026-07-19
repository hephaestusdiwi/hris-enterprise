<?php

namespace App\Modules\Holiday\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Holiday\Models\Holiday;
use App\Modules\Holiday\Requests\StoreHolidayRequest;
use App\Modules\Holiday\Requests\UpdateHolidayRequest;

class HolidayController extends Controller
{
    public function index()
    {
        $holidays = Holiday::with('company')->orderBy('date')->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $holidays,
        ]);
    }

    public function store(StoreHolidayRequest $request)
    {
        $holiday = Holiday::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Holiday berhasil dibuat',
            'data' => $holiday->load('company'),
        ], 201);
    }

    public function show(Holiday $holiday)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $holiday->load('company'),
        ]);
    }

    public function update(UpdateHolidayRequest $request, Holiday $holiday)
    {
        $holiday->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Holiday berhasil diperbarui',
            'data' => $holiday->load('company'),
        ]);
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->delete();

        return response()->json([
            'success' => true,
            'message' => 'Holiday berhasil dihapus',
            'data' => null,
        ]);
    }
}