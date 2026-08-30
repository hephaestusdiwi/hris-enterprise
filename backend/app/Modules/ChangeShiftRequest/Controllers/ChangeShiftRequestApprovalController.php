<?php

namespace App\Modules\ChangeShiftRequest\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ChangeShiftRequest\Exceptions\ChangeShiftRequestApprovalException;
use App\Modules\ChangeShiftRequest\Models\ChangeShiftRequestApprovalStepDecision;
use App\Modules\ChangeShiftRequest\Requests\DecideChangeShiftRequestApprovalRequest;
use App\Modules\ChangeShiftRequest\Services\ChangeShiftRequestApprovalService;
use Illuminate\Http\Request;

class ChangeShiftRequestApprovalController extends Controller
{
    public function __construct(private ChangeShiftRequestApprovalService $service)
    {
    }

    public function index(Request $request)
    {
        $decisions = $this->service->pendingDecisionsForUser($request->user());

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $decisions]);
    }

    public function decide(DecideChangeShiftRequestApprovalRequest $request, ChangeShiftRequestApprovalStepDecision $decision)
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
        } catch (ChangeShiftRequestApprovalException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }
}