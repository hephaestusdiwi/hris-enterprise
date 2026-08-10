<?php

namespace App\Modules\Candidate\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Candidate\Models\Candidate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Candidate::class);

        $candidates = Candidate::query()
            ->when($request->integer('job_vacancy_id'), fn ($q, $v) => $q->where('job_vacancy_id', $v))
            ->when($request->string('status')->toString(), fn ($q, $v) => $q->where('status', $v))
            ->with('jobVacancy')
            ->latest('applied_at')
            ->paginate();

        return response()->json([
            'success' => true,
            'message' => 'Daftar Candidate berhasil diambil.',
            'data' => $candidates,
        ]);
    }

    public function show(Candidate $candidate): JsonResponse
    {
        $this->authorize('view', $candidate);

        return response()->json([
            'success' => true,
            'message' => 'Detail Candidate berhasil diambil.',
            'data' => $candidate->load(['jobVacancy', 'stageHistories.changedBy']),
        ]);
    }
}