<?php

namespace App\Modules\Pph21\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pph21\Models\TerRateBracket;
use App\Modules\Pph21\Requests\StoreTerRateBracketRequest;
use Illuminate\Http\Request;

class TerRateBracketController extends Controller
{
    public function index(Request $request)
    {
        $brackets = TerRateBracket::query()
            ->when($request->query('category'), fn ($q, $v) => $q->where('category', $v))
            ->orderBy('category')
            ->orderByDesc('effective_date')
            ->orderBy('income_from')
            ->get();

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $brackets]);
    }

    public function store(StoreTerRateBracketRequest $request)
    {
        $bracket = TerRateBracket::create($request->validated());

        return response()->json(['success' => true, 'message' => 'Bracket TER berhasil ditambahkan', 'data' => $bracket], 201);
    }

    public function destroy(TerRateBracket $terRateBracket)
    {
        if ($terRateBracket->effective_date->isPast() || $terRateBracket->effective_date->isToday()) {
            return response()->json([
                'success' => false,
                'message' => 'Bracket yang sudah berlaku tidak bisa dihapus.',
                'data' => null,
            ], 422);
        }

        $terRateBracket->delete();

        return response()->json(['success' => true, 'message' => 'Bracket berhasil dihapus', 'data' => null]);
    }
}