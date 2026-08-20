<?php

namespace App\Modules\CashAdvance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CashAdvance\Models\CashAdvanceRequest;
use App\Modules\CashAdvance\Requests\CancelCashAdvanceRequest;
use App\Modules\CashAdvance\Requests\DisburseCashAdvanceRequest;
use App\Modules\CashAdvance\Requests\StoreCashAdvanceRequest;
use App\Modules\CashAdvance\Services\CashAdvanceService;
use Illuminate\Http\Request;

class CashAdvanceController extends Controller
{
    public function __construct(
        private CashAdvanceService $cashAdvanceService,
    ) {
    }

    public function index(Request $request)
    {
        $query = CashAdvanceRequest::query()
            ->with([
                'employee',
                'policy',
                'items.category',
            ])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('cash_advance_policy_id')) {
            $query->where(
                'cash_advance_policy_id',
                $request->integer('cash_advance_policy_id'),
            );
        }

        if ($request->filled('search')) {
            $search = $request->string('search');

            $query->where(function ($q) use ($search) {
                $q->where('purpose', 'ilike', "%{$search}%")
                    ->orWhereHas('employee', function ($employee) use ($search) {
                        $employee
                            ->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%")
                            ->orWhere('employee_number', 'ilike', "%{$search}%");
                    });
            });
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $query->paginate(15),
        ]);
    }

    public function show(CashAdvanceRequest $cashAdvance)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $cashAdvance->load([
                'employee.department',
                'policy',
                'items.category',
                'attachments',
                'approvalRequest.stepDecisions.approvalStep',
                'disbursedBy',
                'settlements.items.category',
                'settlements.attachments',
                'settlements.approvalRequest.stepDecisions.approvalStep',
            ]),
        ]);
    }

    public function myCashAdvances(Request $request)
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json([
                'success' => true,
                'message' => 'OK',
                'data' => [],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => CashAdvanceRequest::query()
                ->with(['policy', 'items.category'])
                ->where('employee_id', $employee->id)
                ->latest()
                ->paginate(15),
        ]);
    }

    public function myCashAdvanceShow(
        Request $request,
        CashAdvanceRequest $cashAdvance,
    ) {
        abort_unless(
            $cashAdvance->employee_id === $request->user()->employee?->id,
            403,
        );

        return $this->show($cashAdvance);
    }

    public function store(StoreCashAdvanceRequest $request)
    {
        $result = $this->cashAdvanceService->submit(
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Cash Advance berhasil diajukan',
            'data' => $result,
        ], 201);
    }

    public function cancel(
        CancelCashAdvanceRequest $request,
        CashAdvanceRequest $cashAdvance,
    ) {
        abort_unless(
            $cashAdvance->employee_id === $request->user()->employee?->id
                || $request->user()->can('cancel cash advances'),
            403,
        );

        $result = $this->cashAdvanceService->cancel(
            $cashAdvance,
            $request->user(),
            $request->validated('reason'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Cash Advance berhasil dibatalkan',
            'data' => $result,
        ]);
    }

    public function disburse(
        DisburseCashAdvanceRequest $request,
        CashAdvanceRequest $cashAdvance,
    ) {
        $result = $this->cashAdvanceService->disburse(
            $cashAdvance,
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Cash Advance berhasil dicairkan',
            'data' => $result,
        ]);
    }
}