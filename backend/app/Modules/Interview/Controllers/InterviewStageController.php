<?php

namespace App\Modules\Interview\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Interview\Models\InterviewStage;
use Illuminate\Http\JsonResponse;

class InterviewStageController extends Controller
{
    public function index(): JsonResponse
    {
        $stages = InterviewStage::where('is_active', true)
            ->orderBy('sequence')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar Interview Stage berhasil diambil',
            'data' => $stages,
        ]);
    }
}