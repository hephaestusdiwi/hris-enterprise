<?php

namespace App\Modules\Holiday\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Holiday\Exceptions\NationalHolidayProviderException;
use App\Modules\Holiday\Requests\ImportNationalHolidaysRequest;
use App\Modules\Holiday\Requests\PreviewNationalHolidaysRequest;
use App\Modules\Holiday\Services\HolidayImportService;
use Illuminate\Http\JsonResponse;

class HolidayImportController extends Controller
{
    public function __construct(
        protected HolidayImportService $importService,
    ) {
    }

    public function preview(PreviewNationalHolidaysRequest $request): JsonResponse
    {
        try {
            $preview = $this->importService->preview((int) $request->validated('year'));
        } catch (NationalHolidayProviderException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 502);
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $preview,
        ]);
    }

    public function import(ImportNationalHolidaysRequest $request): JsonResponse
    {
        try {
            $imported = $this->importService->import(
                (int) $request->validated('year'),
                $request->validated('external_ids'),
            );
        } catch (NationalHolidayProviderException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 502);
        }

        return response()->json([
            'success' => true,
            'message' => "{$imported->count()} hari libur nasional berhasil diimport",
            'data' => $imported,
        ]);
    }
}