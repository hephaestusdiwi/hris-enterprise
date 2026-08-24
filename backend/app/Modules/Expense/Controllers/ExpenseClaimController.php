<?php

namespace App\Modules\Expense\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Expense\Exceptions\ExpenseClaimValidationException;
use App\Modules\Expense\Models\ExpenseClaim;
use App\Modules\Expense\Requests\CancelExpenseClaimRequest;
use App\Modules\Expense\Requests\StoreExpenseClaimRequest;
use App\Modules\Expense\Services\ExpenseClaimService;
use Illuminate\Http\Request;

class ExpenseClaimController extends Controller
{
    public function __construct(
        private ExpenseClaimService $expenseClaimService,
    ) {
    }

    public function index(Request $request)
    {
        $query = ExpenseClaim::with(['employee', 'category', 'subcategory'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $query->paginate(20),
        ]);
    }

    public function show(ExpenseClaim $expenseClaim)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $expenseClaim->load([
                'employee.department',
                'category',
                'subcategory',
                'attachments',
                'policyAssignment.policy',
                'approvalRequest.stepDecisions.approvalStep',
                'paidBy',
            ]),
        ]);
    }

    // ---- Self-service ----

    public function myClaims(Request $request)
    {
        $employee = $request->user()->employee;

        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee.');

        $claims = ExpenseClaim::with(['category', 'subcategory', 'attachments'])
            ->where('employee_id', $employee->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $claims,
        ]);
    }

    public function myClaimShow(Request $request, ExpenseClaim $expenseClaim)
    {
        $employee = $request->user()->employee;

        abort_if(
            ! $employee || $expenseClaim->employee_id !== $employee->id,
            403,
            'Anda tidak berhak melihat claim ini.'
        );

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $expenseClaim->load([
                'category',
                'subcategory',
                'attachments',
                'policyAssignment.policy',
                'approvalRequest.stepDecisions.approvalStep',
            ]),
        ]);
    }

    public function store(StoreExpenseClaimRequest $request)
    {
        $employee = $request->user()->employee;

        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee.');

        try {
            $claim = $this->expenseClaimService->submit($employee, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Expense Claim berhasil diajukan',
                'data' => $claim,
            ], 201);
        } catch (ExpenseClaimValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }

    public function cancel(CancelExpenseClaimRequest $request, ExpenseClaim $expenseClaim)
    {
        abort_unless(
            $expenseClaim->employee_id === $request->user()->employee?->id
                || $request->user()->can('cancel expense claims'),
            403,
        );

        try {
            $claim = $this->expenseClaimService->cancel(
                $expenseClaim,
                $request->validated('reason')
            );

            return response()->json([
                'success' => true,
                'message' => 'Expense Claim berhasil dibatalkan',
                'data' => $claim,
            ]);
        } catch (ExpenseClaimValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }
}