<?php

namespace App\Modules\HiringRequisition\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HiringRequisition\Models\HiringRequisitionApprovalStepDecision;
use App\Modules\HiringRequisition\Requests\DecideHiringRequisitionApprovalRequest;
use App\Modules\HiringRequisition\Services\HiringRequisitionApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HiringRequisitionApprovalController extends Controller
{
    public function __construct(
        private HiringRequisitionApprovalService $service,
    ) {
    }

    public function pending(Request $request): JsonResponse
    {
        $decisions = $this->service->pendingDecisionsForUser($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Daftar approval pending berhasil diambil.',
            'data' => $decisions,
        ]);
    }

    public function decide(
        HiringRequisitionApprovalStepDecision $decision,
        DecideHiringRequisitionApprovalRequest $request,
    ): JsonResponse {
        $result = $this->service->decide(
            $decision,
            $request->user(),
            $request->validated('action'),
            $request->validated('notes'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Keputusan approval berhasil disimpan.',
            'data' => $result->load('stepDecisions'),
        ]);
    }
}