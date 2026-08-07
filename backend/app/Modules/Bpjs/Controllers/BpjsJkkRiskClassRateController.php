<?php

namespace App\Modules\Bpjs\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Bpjs\Models\BpjsJkkRiskClassRate;
use App\Modules\Bpjs\Requests\StoreBpjsJkkRiskClassRateRequest;

class BpjsJkkRiskClassRateController extends Controller
{
    public function index()
    {
        $rates = BpjsJkkRiskClassRate::orderBy('risk_class')->orderByDesc('effective_date')->get();

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $rates]);
    }

    public function store(StoreBpjsJkkRiskClassRateRequest $request)
    {
        $rate = BpjsJkkRiskClassRate::create($request->validated());

        return response()->json(['success' => true, 'message' => 'Tarif kelas risiko JKK berhasil ditambahkan', 'data' => $rate], 201);
    }
}