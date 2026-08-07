<?php

namespace App\Modules\Bpjs\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Bpjs\Models\BpjsRateConfig;
use App\Modules\Bpjs\Requests\StoreBpjsRateConfigRequest;
use Illuminate\Http\Request;

class BpjsRateConfigController extends Controller
{
    public function index(Request $request)
    {
        $configs = BpjsRateConfig::query()
            ->when($request->query('company_id'), fn ($q, $v) => $q->where('company_id', $v))
            ->when($request->query('program'), fn ($q, $v) => $q->where('program', $v))
            ->orderByDesc('effective_date')
            ->get();

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $configs]);
    }

    public function store(StoreBpjsRateConfigRequest $request)
    {
        $config = BpjsRateConfig::create($request->validated());

        return response()->json(['success' => true, 'message' => 'Rate config berhasil ditambahkan', 'data' => $config], 201);
    }

    /**
     * Rate config adalah histori — hanya boleh dihapus kalau belum pernah
     * "berlaku" (effective_date di masa depan), supaya payroll yang sudah
     * jalan dengan tarif ini tidak kehilangan jejak.
     */
    public function destroy(BpjsRateConfig $bpjsRateConfig)
    {
        if ($bpjsRateConfig->effective_date->isPast() || $bpjsRateConfig->effective_date->isToday()) {
            return response()->json([
                'success' => false,
                'message' => 'Rate config yang sudah berlaku (effective_date <= hari ini) tidak bisa dihapus.',
                'data' => null,
            ], 422);
        }

        $bpjsRateConfig->delete();

        return response()->json(['success' => true, 'message' => 'Rate config berhasil dihapus', 'data' => null]);
    }
}