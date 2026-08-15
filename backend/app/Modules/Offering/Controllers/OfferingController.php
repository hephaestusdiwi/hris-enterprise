<?php

namespace App\Modules\Offering\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Candidate\Models\Candidate;
use App\Modules\Offering\Enums\OfferingStatus;
use App\Modules\Offering\Models\Offering;
use App\Modules\Offering\Requests\RespondOfferingRequest;
use App\Modules\Offering\Requests\StoreOfferingRequest;
use App\Modules\Offering\Requests\UpdateOfferingRequest;
use App\Modules\Offering\Services\OfferingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfferingController extends Controller
{
    public function __construct(private OfferingService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Offering::class);

        $offerings = Offering::query()
            ->when($request->integer('candidate_id'), fn ($q, $v) => $q->where('candidate_id', $v))
            ->when($request->string('status')->toString(), fn ($q, $v) => $q->where('status', $v))
            ->with('candidate.jobVacancy')
            ->latest()
            ->paginate();

        return response()->json(['success' => true, 'message' => 'Daftar Offering berhasil diambil.', 'data' => $offerings]);
    }

    public function store(StoreOfferingRequest $request): JsonResponse
    {
        $this->authorize('create', Offering::class);

        $candidate = Candidate::findOrFail($request->validated('candidate_id'));
        $offering = $this->service->create($candidate, $request->user(), $request->validated());

        return response()->json(['success' => true, 'message' => 'Offering berhasil dibuat.', 'data' => $offering], 201);
    }

    public function show(Offering $offering): JsonResponse
    {
        $this->authorize('view', $offering);

        return response()->json([
            'success' => true,
            'message' => 'Detail Offering berhasil diambil.',
            'data' => $offering->load(['candidate.jobVacancy.position', 'candidate.jobVacancy.department', 'candidate.jobVacancy.employmentType', 'createdBy']),
        ]);
    }

    public function update(Offering $offering, UpdateOfferingRequest $request): JsonResponse
    {
        $this->authorize('update', $offering);

        return response()->json(['success' => true, 'message' => 'Offering berhasil diperbarui.', 'data' => $this->service->update($offering, $request->validated())]);
    }

    public function send(Offering $offering): JsonResponse
    {
        $this->authorize('send', $offering);

        return response()->json(['success' => true, 'message' => 'Offering berhasil dikirim.', 'data' => $this->service->send($offering, request()->user())]);
    }

    public function respond(Offering $offering, RespondOfferingRequest $request): JsonResponse
    {
        $this->authorize('respond', $offering);

        $response = OfferingStatus::from($request->validated('response'));

        return response()->json(['success' => true, 'message' => 'Respon Offering berhasil dicatat.', 'data' => $this->service->respond($offering, $response, $request->validated('notes'))]);
    }

    public function withdraw(Offering $offering): JsonResponse
    {
        $this->authorize('withdraw', $offering);

        return response()->json(['success' => true, 'message' => 'Offering berhasil ditarik.', 'data' => $this->service->withdraw($offering)]);
    }
}