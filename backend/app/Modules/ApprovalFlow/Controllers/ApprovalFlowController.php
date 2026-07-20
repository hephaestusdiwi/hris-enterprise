<?php

namespace App\Modules\ApprovalFlow\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\ApprovalFlow\Requests\StoreApprovalFlowRequest;
use App\Modules\ApprovalFlow\Requests\UpdateApprovalFlowRequest;
use Spatie\Permission\Models\Role;

class ApprovalFlowController extends Controller
{
    public function index()
    {
        $approvalFlows = ApprovalFlow::with(['company', 'branch', 'department'])
            ->withCount('steps')
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $approvalFlows,
        ]);
    }

    public function roles()
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => Role::select('id', 'name')->get(),
        ]);
    }

    public function store(StoreApprovalFlowRequest $request)
    {
        $approvalFlow = ApprovalFlow::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Approval Flow berhasil dibuat',
            'data' => $approvalFlow->load(['company', 'branch', 'department']),
        ], 201);
    }

    public function show(ApprovalFlow $approvalFlow)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $approvalFlow->load(['company', 'branch', 'department', 'steps', 'assignments.employee']),
        ]);
    }

    public function update(UpdateApprovalFlowRequest $request, ApprovalFlow $approvalFlow)
    {
        $approvalFlow->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Approval Flow berhasil diperbarui',
            'data' => $approvalFlow->load(['company', 'branch', 'department']),
        ]);
    }

    public function destroy(ApprovalFlow $approvalFlow)
    {
        $approvalFlow->delete();

        return response()->json([
            'success' => true,
            'message' => 'Approval Flow berhasil dihapus',
            'data' => null,
        ]);
    }
}