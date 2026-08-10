<?php

namespace App\Modules\EmployeeMovement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\EmployeeMovement\Models\EmployeeMovementApprovalStepDecision;
use App\Modules\EmployeeMovement\Requests\DecideEmployeeMovementApprovalRequest;
use App\Modules\EmployeeMovement\Services\EmployeeMovementApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeMovementApprovalController extends Controller
{
    public function __construct(
        private EmployeeMovementApprovalService $service,
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
        EmployeeMovementApprovalStepDecision $decision,
        DecideEmployeeMovementApprovalRequest $request,
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
