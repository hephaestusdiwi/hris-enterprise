<?php

namespace App\Modules\ApprovalFlow\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\ApprovalFlow\Models\ApprovalFlowAssignment;
use App\Modules\ApprovalFlow\Requests\StoreApprovalFlowAssignmentRequest;
use App\Modules\ApprovalFlow\Requests\UpdateApprovalFlowAssignmentRequest;

class ApprovalFlowAssignmentController extends Controller
{
    public function index(ApprovalFlow $approvalFlow)
    {
        $assignments = $approvalFlow->assignments()
            ->with('employee')
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $assignments,
        ]);
    }

    public function store(StoreApprovalFlowAssignmentRequest $request, ApprovalFlow $approvalFlow)
    {
        $assignment = $approvalFlow->assignments()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Employee berhasil di-assign ke Approval Flow',
            'data' => $assignment->load('employee'),
        ], 201);
    }

    public function show(ApprovalFlowAssignment $assignment)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $assignment->load(['employee', 'approvalFlow']),
        ]);
    }

    public function update(UpdateApprovalFlowAssignmentRequest $request, ApprovalFlowAssignment $assignment)
    {
        $assignment->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Assignment berhasil diperbarui',
            'data' => $assignment->load(['employee', 'approvalFlow']),
        ]);
    }

    public function destroy(ApprovalFlowAssignment $assignment)
    {
        $assignment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Assignment berhasil dihapus',
            'data' => null,
        ]);
    }
}