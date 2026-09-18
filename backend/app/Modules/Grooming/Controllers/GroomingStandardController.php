<?php

namespace App\Modules\Grooming\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Grooming\Enums\GroomingType;
use App\Modules\Grooming\Models\GroomingStandard;
use App\Modules\Grooming\Requests\StoreGroomingStandardRequest;
use App\Modules\Grooming\Requests\StoreGroomingStandardVersionRequest;
use App\Modules\Grooming\Requests\UpdateGroomingStandardRequest;
use App\Modules\Grooming\Services\GroomingStandardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroomingStandardController extends Controller
{
    public function __construct(
        private GroomingStandardService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('manage', GroomingStandard::class);

        $standards = GroomingStandard::query()
            ->when($request->string('type')->toString(), fn ($q, $v) => $q->where('type', $v))
            ->withCount('items')
            ->orderByDesc('id')
            ->paginate();

        return response()->json([
            'success' => true,
            'message' => 'Daftar Grooming Standard berhasil diambil.',
            'data' => $standards,
        ]);
    }

    public function show(GroomingStandard $groomingStandard): JsonResponse
    {
        $this->authorize('manage', GroomingStandard::class);

        return response()->json([
            'success' => true,
            'message' => 'Detail Grooming Standard berhasil diambil.',
            'data' => $groomingStandard->load('items', 'createdBy'),
        ]);
    }

    public function store(StoreGroomingStandardRequest $request): JsonResponse
    {
        $this->authorize('manage', GroomingStandard::class);

        $type = GroomingType::from($request->route('type'));
        $standard = $this->service->create($type, $request->validated(), $request->user()->employee?->id);

        return response()->json([
            'success' => true,
            'message' => 'Grooming Standard berhasil dibuat sebagai Draft.',
            'data' => $standard,
        ], 201);
    }

    public function update(GroomingStandard $groomingStandard, UpdateGroomingStandardRequest $request): JsonResponse
    {
        $this->authorize('manage', GroomingStandard::class);

        $standard = $this->service->updateDraft($groomingStandard, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Grooming Standard berhasil diperbarui.',
            'data' => $standard,
        ]);
    }

    public function createVersion(GroomingStandard $groomingStandard, StoreGroomingStandardVersionRequest $request): JsonResponse
    {
        $this->authorize('manage', GroomingStandard::class);

        $data = $request->validated();
        $data['created_by_employee_id'] = $request->user()->employee?->id;

        $standard = $this->service->createNewVersion($groomingStandard, $data);

        return response()->json([
            'success' => true,
            'message' => "Versi baru (v{$standard->version_number}) berhasil dibuat sebagai Draft.",
            'data' => $standard,
        ], 201);
    }

    public function activate(GroomingStandard $groomingStandard): JsonResponse
    {
        $this->authorize('manage', GroomingStandard::class);

        $standard = $this->service->activate($groomingStandard);

        return response()->json([
            'success' => true,
            'message' => 'Grooming Standard berhasil diaktifkan.',
            'data' => $standard,
        ]);
    }

    public function archive(GroomingStandard $groomingStandard): JsonResponse
    {
        $this->authorize('manage', GroomingStandard::class);

        $standard = $this->service->archive($groomingStandard);

        return response()->json([
            'success' => true,
            'message' => 'Grooming Standard berhasil di-archive.',
            'data' => $standard,
        ]);
    }
}