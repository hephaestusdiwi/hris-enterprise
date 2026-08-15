<?php

namespace App\Modules\NewJoiner\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Candidate\Models\Candidate;
use App\Modules\NewJoiner\Models\NewJoiner;
use App\Modules\NewJoiner\Requests\SendNewJoinerRequest;
use App\Modules\NewJoiner\Services\NewJoinerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewJoinerController extends Controller
{
    public function __construct(
        private NewJoinerService $service,
        private NewJoinerConversionService $conversionService, // BARU
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', NewJoiner::class);

        $newJoiners = NewJoiner::query()
            ->when($request->integer('candidate_id'), fn ($q, $v) => $q->where('candidate_id', $v))
            ->when($request->string('status')->toString(), fn ($q, $v) => $q->where('status', $v))
            ->with('candidate')
            ->latest('sent_at')
            ->paginate();

        return response()->json(['success' => true, 'message' => 'Daftar New Joiner berhasil diambil.', 'data' => $newJoiners]);
    }

    public function store(SendNewJoinerRequest $request): JsonResponse
    {
        $this->authorize('create', NewJoiner::class);

        $candidate = Candidate::findOrFail($request->validated('candidate_id'));
        $newJoiner = $this->service->send($candidate, $request->user(), $request->validated('expires_in_days'));

        return response()->json(['success' => true, 'message' => 'New Joiner form berhasil dikirim.', 'data' => $newJoiner], 201);
    }

    public function show(NewJoiner $newJoiner): JsonResponse
    {
        $this->authorize('view', $newJoiner);

        return response()->json(['success' => true, 'message' => 'Detail New Joiner berhasil diambil.', 'data' => $newJoiner->load('candidate.jobVacancy')]);
    }

    public function proceedAsEmployee(NewJoiner $newJoiner): JsonResponse
    {
        $this->authorize('proceedAsEmployee', NewJoiner::class);

        return response()->json([
            'success' => true,
            'message' => 'New Joiner siap diproses sebagai Employee (lanjut di Phase 7C).',
            'data' => $this->service->markReadyForEmployee($newJoiner),
        ]);
    }

    public function convertToEmployee(NewJoiner $newJoiner, ConvertNewJoinerRequest $request): JsonResponse
    {
        $this->authorize('proceedAsEmployee', NewJoiner::class); // reuse permission Phase 7B, tidak bikin baru

        $employee = $this->conversionService->convertToEmployee($newJoiner, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'New Joiner berhasil dikonversi menjadi Employee.',
            'data' => $employee->load(['company', 'branch', 'department', 'position', 'employmentType']),
        ], 201);
    }
}