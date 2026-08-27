<?php

namespace App\Modules\OvertimeRequest\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\OvertimeRequest\Exceptions\OvertimeRequestApprovalException;
use App\Modules\OvertimeRequest\Models\OvertimeRequestApprovalStepDecision;
use App\Modules\OvertimeRequest\Requests\DecideOvertimeRequestApprovalRequest;
use App\Modules\OvertimeRequest\Services\OvertimeRequestApprovalService;
use Illuminate\Http\Request;

class OvertimeRequestApprovalController extends Controller
{
    public function __construct(private OvertimeRequestApprovalService $service)
    {
    }

    public function index(Request $request)
    {
        $decisions = $this->service->pendingDecisionsForUser($request->user());

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $decisions]);
    }

    public function decide(DecideOvertimeRequestApprovalRequest $request, OvertimeRequestApprovalStepDecision $decision)
    {
        try {
            $result = $this->service->decide(
                $decision,
                $request->user(),
                $request->validated('action'),
                $request->validated('notes'),
            );

            return response()->json([
                'success' => true,
                'message' => 'Keputusan berhasil dicatat',
                'data' => $result,
            ]);
        } catch (OvertimeRequestApprovalException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }
}