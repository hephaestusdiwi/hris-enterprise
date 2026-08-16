<?php

namespace App\Modules\Reimbursement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reimbursement\Models\ReimbursementPolicy;
use App\Modules\Reimbursement\Requests\StoreReimbursementPolicyRequest;
use App\Modules\Reimbursement\Requests\UpdateReimbursementPolicyRequest;

class ReimbursementPolicyController extends Controller
{
    public function index()
    {
        $policies = ReimbursementPolicy::withCount('benefits')->orderByDesc('id')->get();

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $policies]);
    }

    public function show(ReimbursementPolicy $reimbursementPolicy)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $reimbursementPolicy->load('benefits'),
        ]);
    }

    public function store(StoreReimbursementPolicyRequest $request)
    {
        $policy = ReimbursementPolicy::create([
            ...$request->validated(),
            'created_by_user_id' => $request->user()?->id,
        ]);

        return response()->json(['success' => true, 'message' => 'Policy berhasil dibuat', 'data' => $policy], 201);
    }

    public function update(UpdateReimbursementPolicyRequest $request, ReimbursementPolicy $reimbursementPolicy)
    {
        $reimbursementPolicy->update($request->validated());

        return response()->json(['success' => true, 'message' => 'Policy berhasil diperbarui', 'data' => $reimbursementPolicy->fresh()]);
    }
}