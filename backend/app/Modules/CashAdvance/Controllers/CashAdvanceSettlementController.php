<?php

namespace App\Modules\CashAdvance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CashAdvance\Exceptions\CashAdvanceApprovalException;
use App\Modules\CashAdvance\Exceptions\CashAdvanceValidationException;
use App\Modules\CashAdvance\Models\CashAdvanceRequest as CashAdvanceRequestModel;
use App\Modules\CashAdvance\Models\CashAdvanceSettlement;
use App\Modules\CashAdvance\Requests\DecideCashAdvanceSettlementApprovalRequest;
use App\Modules\CashAdvance\Requests\StoreCashAdvanceSettlementRequest;
use App\Modules\CashAdvance\Services\CashAdvanceSettlementApprovalService;
use App\Modules\CashAdvance\Services\CashAdvanceSettlementService;
use Illuminate\Http\Request;

class CashAdvanceSettlementController extends Controller
{
    public function __construct(
        private CashAdvanceSettlementService $settlementService,
        private CashAdvanceSettlementApprovalService $settlementApprovalService,
    ) {
    }

    public function index(Request $request)
    {
        $decisions = $this->settlementApprovalService->pendingDecisionsForUser($request->user());

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $decisions]);
    }

    public function show(Request $request, CashAdvanceRequestModel $cashAdvance)
    {
        $employee = $request->user()->employee;
        $isOwner = $employee && $cashAdvance->employee_id === $employee->id;

        abort_if(! $isOwner && ! $request->user()->can('view cash advance settlements'), 403, 'Anda tidak berhak melihat settlement request ini.');

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $cashAdvance->settlements()->with(['items.category', 'attachments', 'approvalRequest.stepDecisions.approvalStep'])->get(),
        ]);
    }

    public function store(StoreCashAdvanceSettlementRequest $request, CashAdvanceRequestModel $cashAdvance)
    {
        $employee = $request->user()->employee;

        abort_if(! $employee || $cashAdvance->employee_id !== $employee->id, 403, 'Hanya pemilik Cash Advance yang dapat submit settlement.');

        try {
            $settlement = $this->settlementService->submit($cashAdvance, $request->validated());

            return response()->json(['success' => true, 'message' => 'Settlement berhasil diajukan', 'data' => $settlement], 201);
        } catch (CashAdvanceValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function decide(DecideCashAdvanceSettlementApprovalRequest $request, CashAdvanceSettlement $settlement)
    {
        $decision = $settlement->approvalRequest?->stepDecisions()
            ->where('sequence', $settlement->approvalRequest->current_step_sequence)
            ->first();

        if (! $decision) {
            return response()->json(['success' => false, 'message' => 'Tidak ada step approval yang aktif untuk settlement ini.', 'data' => null], 422);
        }

        try {
            $result = $this->settlementApprovalService->decide(
                $decision,
                $request->user(),
                $request->validated('action'),
                $request->validated('notes'),
            );

            return response()->json([
                'success' => true,
                'message' => $request->validated('action') === 'approve' ? 'Settlement berhasil di-approve' : 'Settlement berhasil ditolak',
                'data' => $result,
            ]);
        } catch (CashAdvanceApprovalException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }
}