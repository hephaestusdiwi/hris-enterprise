<?php

namespace App\Modules\Pph21\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pph21\Models\PtkpConfig;
use App\Modules\Pph21\Requests\StorePtkpConfigRequest;

class PtkpConfigController extends Controller
{
    public function index()
    {
        $configs = PtkpConfig::orderBy('ptkp_status')->orderByDesc('effective_date')->get();

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $configs]);
    }

    public function store(StorePtkpConfigRequest $request)
    {
        $config = PtkpConfig::create($request->validated());

        return response()->json(['success' => true, 'message' => 'PTKP config berhasil ditambahkan', 'data' => $config], 201);
    }
}