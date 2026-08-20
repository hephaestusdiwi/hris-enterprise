<?php

namespace App\Modules\CashAdvance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CashAdvance\Exceptions\CashAdvanceApprovalException;
use App\Modules\CashAdvance\Models\CashAdvanceApprovalStepDecision;
use App\Modules\CashAdvance\Requests\DecideCashAdvanceApprovalRequest;
use App\Modules\CashAdvance\Services\CashAdvanceApprovalService;
use Illuminate\Http\Request;

class CashAdvanceApprovalController extends Controller
{
    public function __construct(
        private CashAdvanceApprovalService $approvalService,
    ) {
    }

    public function index(Request $request)
    {
        $decisions = $this->approvalService
            ->pendingDecisionsForUser($request->user());

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $decisions,
        ]);
    }

    public function decide(
        DecideCashAdvanceApprovalRequest $request,
        CashAdvanceApprovalStepDecision $decision,
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
        } catch (CashAdvanceApprovalException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }
}