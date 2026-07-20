<?php

namespace App\Modules\ApprovalFlow\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\ApprovalFlow\Models\ApprovalStep;
use App\Modules\ApprovalFlow\Requests\StoreApprovalStepRequest;
use App\Modules\ApprovalFlow\Requests\UpdateApprovalStepRequest;

class ApprovalStepController extends Controller
{
    public function index(ApprovalFlow $approvalFlow)
    {
        $steps = $approvalFlow->steps()
            ->with(['approverEmployee', 'approverRole'])
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $steps,
        ]);
    }

    public function store(StoreApprovalStepRequest $request, ApprovalFlow $approvalFlow)
    {
        $step = $approvalFlow->steps()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Approval Step berhasil dibuat',
            'data' => $step->load(['approverEmployee', 'approverRole']),
        ], 201);
    }

    public function show(ApprovalFlow $approvalFlow, ApprovalStep $step)
    {
        abort_unless($step->approval_flow_id === $approvalFlow->id, 404);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $step->load(['approverEmployee', 'approverRole']),
        ]);
    }

    public function update(UpdateApprovalStepRequest $request, ApprovalFlow $approvalFlow, ApprovalStep $step)
    {
        abort_unless($step->approval_flow_id === $approvalFlow->id, 404);

        $step->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Approval Step berhasil diperbarui',
            'data' => $step->load(['approverEmployee', 'approverRole']),
        ]);
    }

    public function destroy(ApprovalFlow $approvalFlow, ApprovalStep $step)
    {
        abort_unless($step->approval_flow_id === $approvalFlow->id, 404);

        $step->delete();

        return response()->json([
            'success' => true,
            'message' => 'Approval Step berhasil dihapus',
            'data' => null,
        ]);
    }
}