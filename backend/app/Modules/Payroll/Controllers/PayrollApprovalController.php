<?php

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Exceptions\PayrollApprovalException;
use App\Modules\Payroll\Models\PayrollApprovalStepDecision;
use App\Modules\Payroll\Requests\DecidePayrollApprovalRequest;
use App\Modules\Payroll\Services\PayrollApprovalService;
use Illuminate\Http\Request;

class PayrollApprovalController extends Controller
{
    public function __construct(private PayrollApprovalService $approvalService)
    {
    }

    public function index(Request $request)
    {
        $decisions = $this->approvalService->pendingDecisionsForUser($request->user());

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $decisions]);
    }

    public function decide(DecidePayrollApprovalRequest $request, PayrollApprovalStepDecision $decision)
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
        } catch (PayrollApprovalException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }
}