<?php

namespace App\Modules\HiringRequisition\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HiringRequisition\Contracts\HiringRequisitionScopeInterface;
use App\Modules\HiringRequisition\Models\HiringRequisition;
use App\Modules\HiringRequisition\Requests\StoreHiringRequisitionRequest;
use App\Modules\HiringRequisition\Services\HiringRequisitionService;
use App\Modules\Position\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HiringRequisitionController extends Controller
{
    public function __construct(
        private HiringRequisitionService $service,
        private HiringRequisitionScopeInterface $hiringRequisitionScope,
    ) {
    }

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', HiringRequisition::class);

        $requisitions = HiringRequisition::query()
            ->with(['position', 'department', 'requestedBy', 'approvalRequest'])
            ->latest('requested_at')
            ->paginate();

        return response()->json([
            'success' => true,
            'message' => 'Daftar Hiring Requisition berhasil diambil.',
            'data' => $requisitions,
        ]);
    }

    public function store(StoreHiringRequisitionRequest $request): JsonResponse
    {
        $this->authorize('create', HiringRequisition::class);

        $position = Position::findOrFail($request->validated('position_id'));

        $hiringRequisition = $this->service->submit(
            $request->user()->employee,
            $position,
            $request->validated(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Hiring Requisition berhasil diajukan.',
            'data' => $hiringRequisition->load('approvalRequest.stepDecisions'),
        ], 201);
    }

    public function show(HiringRequisition $hiringRequisition): JsonResponse
    {
        $this->authorize('view', $hiringRequisition);

        return response()->json([
            'success' => true,
            'message' => 'Detail Hiring Requisition berhasil diambil.',
            'data' => $hiringRequisition->load(['position', 'department', 'requestedBy', 'replacementFor', 'approvalRequest.stepDecisions']),
        ]);
    }

    public function cancel(HiringRequisition $hiringRequisition): JsonResponse
    {
        $this->authorize('cancel', $hiringRequisition);

        $hiringRequisition = $this->service->cancel($hiringRequisition);

        return response()->json([
            'success' => true,
            'message' => 'Hiring Requisition berhasil dibatalkan.',
            'data' => $hiringRequisition,
        ]);
    }
}