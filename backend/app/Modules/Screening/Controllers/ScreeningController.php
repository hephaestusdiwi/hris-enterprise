<?php

namespace App\Modules\Screening\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Candidate\Models\Candidate;
use App\Modules\Employee\Models\Employee;
use App\Modules\Screening\Enums\ScreeningResult;
use App\Modules\Screening\Models\Screening;
use App\Modules\Screening\Requests\DecideScreeningRequest;
use App\Modules\Screening\Requests\StoreScreeningRequest;
use App\Modules\Screening\Services\ScreeningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScreeningController extends Controller
{
    public function __construct(
        private ScreeningService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Screening::class);

        $screenings = Screening::query()
            ->when($request->integer('candidate_id'), fn ($q, $v) => $q->where('candidate_id', $v))
            ->when($request->string('status')->toString(), fn ($q, $v) => $q->where('status', $v))
            ->with(['candidate', 'reviewer'])
            ->latest()
            ->paginate();

        return response()->json([
            'success' => true,
            'message' => 'Daftar Screening berhasil diambil.',
            'data' => $screenings,
        ]);
    }

    public function store(StoreScreeningRequest $request): JsonResponse
    {
        $this->authorize('create', Screening::class);

        $candidate = Candidate::findOrFail($request->validated('candidate_id'));
        $reviewer = Employee::findOrFail($request->validated('reviewer_employee_id'));

        $screening = $this->service->start($candidate, $reviewer, $request->user(), $request->validated('notes'));

        return response()->json([
            'success' => true,
            'message' => 'Screening berhasil dimulai.',
            'data' => $screening,
        ], 201);
    }

    public function show(Screening $screening): JsonResponse
    {
        $this->authorize('view', $screening);

        return response()->json([
            'success' => true,
            'message' => 'Detail Screening berhasil diambil.',
            'data' => $screening->load(['candidate', 'reviewer']),
        ]);
    }

    public function decide(Screening $screening, DecideScreeningRequest $request): JsonResponse
    {
        $this->authorize('decide', $screening);

        $result = ScreeningResult::from($request->validated('result'));
        $screening = $this->service->decide($screening, $result, $request->user(), $request->validated('notes'));

        return response()->json([
            'success' => true,
            'message' => 'Keputusan Screening berhasil disimpan.',
            'data' => $screening,
        ]);
    }
}