<?php

namespace App\Modules\Candidate\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Candidate\Models\Candidate;
use App\Modules\Candidate\Requests\ReconsiderCandidateRequest;
use App\Modules\Candidate\Services\CandidateService;
use App\Modules\Candidate\Exceptions\CandidateValidationException;
use App\Modules\NewJoiner\Services\NewJoinerService;
use App\Modules\JobVacancy\Models\JobVacancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateController extends Controller
{
    public function __construct(
        private CandidateService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Candidate::class);

        $candidates = Candidate::query()
            ->when(
                $request->integer('job_vacancy_id'),
                fn ($q, $v) => $q->where('job_vacancy_id', $v)
            )
            ->when(
                $request->string('status')->toString(),
                fn ($q, $v) => $q->where('status', $v)
            )
            ->when(
                $request->string('search')->toString(),
                fn ($q, $v) => $q->where(
                    fn ($query) => $query
                        ->where('full_name', 'like', "%{$v}%")
                        ->orWhere('email', 'like', "%{$v}%")
                )
            )
            ->with('jobVacancy')
            ->latest('applied_at')
            ->paginate();

        return response()->json([
            'success' => true,
            'message' => 'Daftar Candidate berhasil diambil.',
            'data' => $candidates,
        ]);
    }

    public function reconsider(Candidate $candidate, ReconsiderCandidateRequest $request): JsonResponse
    {
        $this->authorize('reconsider', $candidate);

        $targetVacancy = JobVacancy::findOrFail($request->validated('job_vacancy_id'));

        try {
            $newCandidate = $this->service->reconsider(
                $candidate,
                $targetVacancy,
                $request->user(),
                $request->validated('notes'),
            );
        } catch (CandidateValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Candidate berhasil direconsider.',
            'data' => $newCandidate,
        ], 201);
    }

    public function hold(Candidate $candidate, Request $request): JsonResponse
    {
        $this->authorize('hold', Candidate::class);

        try {
            $candidate = $this->service->holdManually(
                $candidate,
                $request->user(),
                $request->input('notes')
            );
        } catch (CandidateValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Candidate berhasil dipindahkan ke Talent Pool.',
            'data' => $candidate,
        ]);
    }

    public function show(Candidate $candidate): JsonResponse
    {
        $this->authorize('view', $candidate);

        return response()->json([
            'success' => true,
            'message' => 'Detail Candidate berhasil diambil.',
            'data' => $candidate->load([
                'jobVacancy',
                'stageHistories.changedBy',
            ]),
        ]);
    }

    public function select(
        Candidate $candidate,
        Request $request
    ): JsonResponse {
        $this->authorize('select', Candidate::class);

        try {
            $candidate = $this->service->select(
                $candidate,
                $request->user(),
                $request->input('notes')
            );
        } catch (CandidateValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Candidate berhasil ditandai Selected.',
            'data' => $candidate,
        ]);
    }

    public function hire(
        Candidate $candidate,
        Request $request
    ): JsonResponse {
        $this->authorize('hire', Candidate::class);

        try {
            $candidate = $this->service->hire(
                $candidate,
                $request->user(),
                $request->input('notes')
            );
        } catch (CandidateValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Candidate berhasil di-Hired.',
            'data' => $candidate,
        ]);
    }
}