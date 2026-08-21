<?php

namespace App\Modules\Candidate\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Candidate\Exceptions\CandidateValidationException;
use App\Modules\Candidate\Requests\StoreCandidateApplicationRequest;
use App\Modules\Candidate\Services\CandidateService;
use App\Modules\JobVacancy\Models\JobVacancy;
use Illuminate\Http\JsonResponse;

class CandidatePublicController extends Controller
{
    public function __construct(
        private CandidateService $service,
    ) {
    }

    public function store(string $slug, StoreCandidateApplicationRequest $request): JsonResponse
    {
        try {
            $vacancy = JobVacancy::where('slug', $slug)->firstOrFail();

            $cvPath = $request->file('cv')->store('candidate-cvs', 'private');

            $candidate = $this->service->apply($vacancy, [
                ...$request->validated(),
                'cv_path' => $cvPath,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Lamaran berhasil dikirim.',
                'data' => [
                    'id' => $candidate->id,
                    'status' => $candidate->status->value,
                ],
            ], 201);
        } catch (CandidateValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }
}