<?php

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Exceptions\AttendanceApprovalException;
use App\Modules\Attendance\Models\AttendanceApprovalStepDecision;
use App\Modules\Attendance\Services\AttendanceApprovalService;
use Illuminate\Http\Request;

class AttendanceApprovalController extends Controller
{
    public function __construct(private AttendanceApprovalService $approvalService)
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

    public function decide(Request $request, AttendanceApprovalStepDecision $decision)
    {
        $validated = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'adjusted_value' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $result = $this->approvalService->decide(
                $decision,
                $request->user(),
                $validated['action'],
                $validated['adjusted_value'] ?? null,
                $validated['notes'] ?? null,
            );

            return response()->json([
                'success' => true,
                'message' => $validated['action'] === 'approve' ? 'Berhasil di-approve' : 'Berhasil ditolak',
                'data' => $result,
            ]);
        } catch (AttendanceApprovalException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }
}