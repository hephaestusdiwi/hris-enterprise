<?php

namespace App\Modules\Expense\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Expense\Models\ExpensePolicyAssignment;
use App\Modules\Expense\Requests\StoreExpensePolicyAssignmentRequest;
use App\Modules\Expense\Requests\UpdateExpensePolicyAssignmentRequest;
use Illuminate\Http\Request;

class ExpensePolicyAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $query = ExpensePolicyAssignment::with(['employee', 'policy'])->latest();

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $query->get(),
        ]);
    }

    public function show(ExpensePolicyAssignment $expensePolicyAssignment)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $expensePolicyAssignment->load(['employee', 'policy']),
        ]);
    }

    public function store(StoreExpensePolicyAssignmentRequest $request)
    {
        $assignment = ExpensePolicyAssignment::create([
            ...$request->validated(),
            'assigned_by_user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Expense Policy berhasil di-assign ke employee',
            'data' => $assignment->load(['employee', 'policy']),
        ], 201);
    }

    public function update(
        UpdateExpensePolicyAssignmentRequest $request,
        ExpensePolicyAssignment $expensePolicyAssignment,
    ) {
        $expensePolicyAssignment->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Assignment berhasil diperbarui',
            'data' => $expensePolicyAssignment->fresh()->load(['employee', 'policy']),
        ]);
    }
}