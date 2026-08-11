<?php

namespace App\Modules\AttendanceRequest\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AttendanceRequest\Exceptions\AttendanceRequestApprovalException;
use App\Modules\AttendanceRequest\Models\AttendanceRequestApprovalStepDecision;
use App\Modules\AttendanceRequest\Requests\DecideAttendanceRequestApprovalRequest;
use App\Modules\AttendanceRequest\Services\AttendanceRequestApprovalService;
use Illuminate\Http\Request;

class AttendanceRequestApprovalController extends Controller
{
    public function __construct(private AttendanceRequestApprovalService $approvalService)
    {
    }

    public function index(Request $request)
    {
        $decisions = $this->approvalService->pendingDecisionsForUser($request->user());

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $decisions]);
    }

    public function decide(DecideAttendanceRequestApprovalRequest $request, AttendanceRequestApprovalStepDecision $decision)
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
        } catch (AttendanceRequestApprovalException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }
}