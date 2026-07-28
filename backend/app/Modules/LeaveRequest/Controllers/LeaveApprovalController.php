<?php

namespace App\Modules\LeaveRequest\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\LeaveRequest\Exceptions\LeaveApprovalException;
use App\Modules\LeaveRequest\Models\LeaveApprovalStepDecision;
use App\Modules\LeaveRequest\Requests\DecideLeaveApprovalRequest;
use App\Modules\LeaveRequest\Services\LeaveApprovalService;
use Illuminate\Http\Request;

class LeaveApprovalController extends Controller
{
    public function __construct(private LeaveApprovalService $approvalService)
    {
    }

    public function index(Request $request)
    {
        $decisions = $this->approvalService->pendingDecisionsForUser($request->user());

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $decisions]);
    }

    public function decide(DecideLeaveApprovalRequest $request, LeaveApprovalStepDecision $decision)
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
        } catch (LeaveApprovalException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }
}