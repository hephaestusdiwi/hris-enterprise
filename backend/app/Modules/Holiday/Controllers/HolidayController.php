<?php

namespace App\Modules\Holiday\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Holiday\Models\Holiday;
use App\Modules\Holiday\Requests\HolidayCalendarRequest;
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

    /**
     * Daftar holiday aktif dalam rentang tanggal tertentu, tanpa pagination.
     * Dipakai oleh modul lain (Leave Calendar, Attendance, Payroll, Overtime, dll)
     * supaya SEMUA modul membaca dari tabel `holidays` yang sama — bukan dari
     * provider atau sumber lain.
     */
    public function calendar(HolidayCalendarRequest $request)
    {
        $companyId = $request->validated('company_id');

        $holidays = Holiday::query()
            ->where('is_active', true)
            ->whereBetween('date', [$request->validated('start'), $request->validated('end')])
            ->when(
                $companyId,
                // holiday khusus company tsb ATAU holiday global (company_id null)
                fn ($query) => $query->where(function ($q) use ($companyId) {
                    $q->whereNull('company_id')->orWhere('company_id', $companyId);
                }),
                // tanpa company_id: hanya holiday global
                fn ($query) => $query->whereNull('company_id')
            )
            ->orderBy('date')
            ->get(['id', 'date', 'name', 'type', 'company_id']);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $holidays,
        ]);
    }

    public function store(StoreHolidayRequest $request)
    {
        $holiday = Holiday::create([
            ...$request->validated(),
            'source' => 'manual',
        ]);

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
        $holiday->update([
            ...$request->validated(),
            'source' => 'manual',
        ]);

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