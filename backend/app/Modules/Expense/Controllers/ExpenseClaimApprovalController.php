<?php

namespace App\Modules\Expense\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Expense\Exceptions\ExpenseClaimApprovalException;
use App\Modules\Expense\Models\ExpenseClaimApprovalStepDecision;
use App\Modules\Expense\Requests\DecideExpenseClaimApprovalRequest;
use App\Modules\Expense\Services\ExpenseClaimApprovalService;
use Illuminate\Http\Request;

class ExpenseClaimApprovalController extends Controller
{
    public function __construct(
        private ExpenseClaimApprovalService $approvalService,
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
        DecideExpenseClaimApprovalRequest $request,
        ExpenseClaimApprovalStepDecision $decision,
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
        } catch (ExpenseClaimApprovalException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }
}