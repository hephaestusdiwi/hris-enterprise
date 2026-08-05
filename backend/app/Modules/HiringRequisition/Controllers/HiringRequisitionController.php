<?php

namespace App\Modules\HiringRequisition\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HiringRequisition\Models\HiringRequisition;
use App\Modules\HiringRequisition\Requests\StoreHiringRequisitionRequest;
use App\Modules\HiringRequisition\Services\HiringRequisitionService;
use App\Modules\Position\Models\Position;
use Illuminate\Http\JsonResponse;

class HiringRequisitionController extends Controller
{
    public function __construct(
        private HiringRequisitionService $service,
    ) {
    }

    public function index(): JsonResponse
    {
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
        return response()->json([
            'success' => true,
            'message' => 'Detail Hiring Requisition berhasil diambil.',
            'data' => $hiringRequisition->load(['position', 'department', 'requestedBy', 'replacementFor', 'approvalRequest.stepDecisions']),
        ]);
    }

    public function cancel(HiringRequisition $hiringRequisition): JsonResponse
    {
        $hiringRequisition = $this->service->cancel($hiringRequisition);

        return response()->json([
            'success' => true,
            'message' => 'Hiring Requisition berhasil dibatalkan.',
            'data' => $hiringRequisition,
        ]);
    }
}