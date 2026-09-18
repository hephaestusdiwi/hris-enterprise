<?php

namespace App\Modules\Grooming\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Branch\Models\Branch;
use App\Modules\Grooming\Models\GroomingStoreSubmission;
use App\Modules\Grooming\Requests\StoreGroomingStoreSubmissionRequest;
use App\Modules\Grooming\Services\GroomingStoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroomingStoreController extends Controller
{
    public function __construct(
        private GroomingStoreService $service,
    ) {
    }

    public function activeStandard(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Grooming Store Standard aktif berhasil diambil.',
            'data' => $this->service->activeStandard(),
        ]);
    }

    /**
     * Branch yang boleh diakses user untuk submit Grooming Store — murni
     * berbasis permission (bukan nama role). Default cuma branch sendiri;
     * permission terpisah 'submit grooming store all branches' buka akses
     * ke semua branch. Dipakai frontend buat nampilin pilihan (atau
     * auto-select kalau cuma 1 opsi).
     */
    public function accessibleBranches(Request $request): JsonResponse
    {
        $user = $request->user();

        $branches = $user->can('submit grooming store all branches')
            ? Branch::where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : Branch::where('id', $user->employee?->branch_id)->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'message' => 'Daftar branch yang bisa diakses berhasil diambil.',
            'data' => $branches,
        ]);
    }

    public function store(StoreGroomingStoreSubmissionRequest $request): JsonResponse
    {
        $branchId = (int) $request->validated('branch_id');
        $this->authorize('submitFor', [GroomingStoreSubmission::class, $branchId]);

        $branch = Branch::findOrFail($branchId);
        $submission = $this->service->submit($branch, $request->user()->employee, $request->validated('answers'));

        return response()->json([
            'success' => true,
            'message' => 'Grooming Store berhasil disubmit.',
            'data' => $submission,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewMonitoring', GroomingStoreSubmission::class);

        $submissions = GroomingStoreSubmission::query()
            ->when($request->integer('branch_id'), fn ($q, $v) => $q->where('branch_id', $v))
            ->when($request->string('result')->toString(), fn ($q, $v) => $q->where('overall_result', $v))
            ->when($request->date('date_from'), fn ($q, $v) => $q->whereDate('submitted_at', '>=', $v))
            ->when($request->date('date_to'), fn ($q, $v) => $q->whereDate('submitted_at', '<=', $v))
            ->with(['branch:id,name', 'submittedBy:id,first_name,last_name'])
            ->latest('submitted_at')
            ->paginate();

        return response()->json([
            'success' => true,
            'message' => 'Monitoring Grooming Store berhasil diambil.',
            'data' => $submissions,
        ]);
    }

    public function show(GroomingStoreSubmission $groomingStoreSubmission): JsonResponse
    {
        $this->authorize('viewMonitoring', GroomingStoreSubmission::class);

        return response()->json([
            'success' => true,
            'message' => 'Detail Grooming Store berhasil diambil.',
            'data' => $groomingStoreSubmission->load(['branch:id,name', 'submittedBy:id,first_name,last_name', 'standard', 'answers.item']),
        ]);
    }
}