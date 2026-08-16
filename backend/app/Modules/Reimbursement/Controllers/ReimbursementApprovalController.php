<?php

namespace App\Modules\Reimbursement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reimbursement\Exceptions\ReimbursementApprovalException;
use App\Modules\Reimbursement\Models\ReimbursementApprovalStepDecision;
use App\Modules\Reimbursement\Requests\DecideReimbursementApprovalRequest;
use App\Modules\Reimbursement\Services\ReimbursementApprovalService;
use Illuminate\Http\Request;

class ReimbursementApprovalController extends Controller
{
    public function __construct(private ReimbursementApprovalService $approvalService)
    {
    }

    public function index(Request $request)
    {
        $decisions = $this->approvalService->pendingDecisionsForUser($request->user());

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $decisions,
        ]);
    }

    public function decide(
        DecideReimbursementApprovalRequest $request,
        ReimbursementApprovalStepDecision $decision
    ) {
        try {
            $result = $this->approvalService->decide(
                $decision,
                $request->user(),
                $request->validated('action'),
                $request->validated('notes'),
            );

            return response()->json([
                'success' => true,
                'message' => $request->validated('action') === 'approve'
                    ? 'Berhasil di-approve'
                    : 'Berhasil ditolak',
                'data' => $result,
            ]);
        } catch (ReimbursementApprovalException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }
}