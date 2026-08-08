<?php

namespace App\Modules\Pph21\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pph21\Models\TaxBracketConfig;
use App\Modules\Pph21\Requests\StoreTaxBracketConfigRequest;

class TaxBracketConfigController extends Controller
{
    public function index()
    {
        $brackets = TaxBracketConfig::orderByDesc('effective_date')->orderBy('income_from')->get();

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $brackets]);
    }

    public function store(StoreTaxBracketConfigRequest $request)
    {
        $bracket = TaxBracketConfig::create($request->validated());

        return response()->json(['success' => true, 'message' => 'Bracket Pasal 17 berhasil ditambahkan', 'data' => $bracket], 201);
    }

    public function destroy(TaxBracketConfig $taxBracketConfig)
    {
        if ($taxBracketConfig->effective_date->isPast() || $taxBracketConfig->effective_date->isToday()) {
            return response()->json([
                'success' => false,
                'message' => 'Bracket yang sudah berlaku tidak bisa dihapus.',
                'data' => null,
            ], 422);
        }

        $taxBracketConfig->delete();

        return response()->json(['success' => true, 'message' => 'Bracket berhasil dihapus', 'data' => null]);
    }
}