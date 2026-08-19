<?php

namespace App\Modules\CashAdvance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CashAdvance\Exceptions\CashAdvanceValidationException;
use App\Modules\CashAdvance\Models\CashAdvanceRequest as CashAdvanceRequestModel;
use App\Modules\CashAdvance\Requests\CancelCashAdvanceRequest;
use App\Modules\CashAdvance\Requests\DisburseCashAdvanceRequest;
use App\Modules\CashAdvance\Requests\StoreCashAdvanceRequest;
use App\Modules\CashAdvance\Services\CashAdvanceService;
use Illuminate\Http\Request;

class CashAdvanceController extends Controller
{
    public function __construct(private CashAdvanceService $cashAdvanceService)
    {
    }

    public function index()
    {
        $cashAdvances = CashAdvanceRequestModel::with(['employee', 'policy', 'items.category'])
            ->when(request('status'), fn ($q) => $q->where('status', request('status')))
            ->when(request('cash_advance_policy_id'), fn ($q) => $q->where('cash_advance_policy_id', request('cash_advance_policy_id')))
            ->when(request('search'), fn ($q) => $q->where('purpose', 'like', '%'.request('search').'%'))
            ->latest()
            ->paginate(20);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $cashAdvances]);
    }

    public function show(CashAdvanceRequestModel $cashAdvance)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $cashAdvance->load([
                'employee.department', 'policy', 'items.category', 'attachments',
                'approvalRequest.stepDecisions.approvalStep', 'disbursedBy',
                'settlements.items.category', 'settlements.attachments', 'settlements.approvalRequest.stepDecisions.approvalStep',
            ]),
        ]);
    }

    // ---- Self-service ----

    public function myCashAdvances(Request $request)
    {
        $employee = $request->user()->employee;

        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee.');

        $cashAdvances = CashAdvanceRequestModel::with(['policy', 'items.category', 'attachments'])
            ->where('employee_id', $employee->id)
            ->latest()
            ->paginate(20);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $cashAdvances]);
    }

    public function myCashAdvanceShow(Request $request, CashAdvanceRequestModel $cashAdvance)
    {
        $employee = $request->user()->employee;

        abort_if(! $employee || $cashAdvance->employee_id !== $employee->id, 403, 'Anda tidak berhak melihat request ini.');

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $cashAdvance->load([
                'policy', 'items.category', 'attachments', 'approvalRequest.stepDecisions.approvalStep',
                'settlements.items.category', 'settlements.attachments', 'settlements.approvalRequest.stepDecisions.approvalStep',
            ]),
        ]);
    }

    public function store(StoreCashAdvanceRequest $request)
    {
        $employee = $request->user()->employee;

        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee.');

        try {
            $cashAdvance = $this->cashAdvanceService->submit($employee, $request->validated());

            return response()->json(['success' => true, 'message' => 'Cash Advance berhasil diajukan', 'data' => $cashAdvance], 201);
        } catch (CashAdvanceValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function cancel(CancelCashAdvanceRequest $request, CashAdvanceRequestModel $cashAdvance)
    {
        $employee = $request->user()->employee;
        $isOwner = $employee && $cashAdvance->employee_id === $employee->id;

        abort_if(! $isOwner && ! $request->user()->can('cancel cash advances'), 403, 'Anda tidak berhak membatalkan request ini.');

        try {
            $cashAdvance = $this->cashAdvanceService->cancel($cashAdvance, $request->validated('reason'));

            return response()->json(['success' => true, 'message' => 'Cash Advance berhasil dibatalkan', 'data' => $cashAdvance]);
        } catch (CashAdvanceValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    // ---- Finance/HR action ----

    public function disburse(DisburseCashAdvanceRequest $request, CashAdvanceRequestModel $cashAdvance)
    {
        try {
            $cashAdvance = $this->cashAdvanceService->disburse($cashAdvance, $request->validated('disbursement_note'), $request->user());

            return response()->json(['success' => true, 'message' => 'Disbursement berhasil dicatat', 'data' => $cashAdvance]);
        } catch (CashAdvanceValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }
}