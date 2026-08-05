<?php

namespace App\Modules\Loan\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Loan\Exceptions\LoanApprovalException;
use App\Modules\Loan\Models\LoanApprovalStepDecision;
use App\Modules\Loan\Requests\DecideLoanApprovalRequest;
use App\Modules\Loan\Services\LoanApprovalService;
use Illuminate\Http\Request;

class LoanApprovalController extends Controller
{
    public function __construct(private LoanApprovalService $approvalService)
    {
    }

    public function index(Request $request)
    {
        $decisions = $this->approvalService->pendingDecisionsForUser($request->user());

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $decisions]);
    }

    public function decide(DecideLoanApprovalRequest $request, LoanApprovalStepDecision $decision)
    {
        try {
            $result = $this->approvalService->decide(
                $decision,
                $request->user(),
                $request->validated('action'),
                $request->validated('notes'),
            );

            return response()->json([
                'success' => true,
                'message' => $request->validated('action') === 'approve' ? 'Berhasil di-approve' : 'Berhasil ditolak',
                'data' => $result,
            ]);
        } catch (LoanApprovalException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }
}