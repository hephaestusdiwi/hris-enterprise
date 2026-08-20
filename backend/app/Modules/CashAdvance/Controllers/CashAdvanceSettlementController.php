<?php

namespace App\Modules\CashAdvance\Controllers;

use App\Http\Controllers\Controller;
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
        private CashAdvanceSettlementApprovalService $approvalService,
    ) {
    }

    public function index(Request $request)
    {
        $query = CashAdvanceSettlement::query()
            ->with([
                'request.employee',
                'items.category',
            ])
            ->latest();

        if (! $request->user()->can('view cash advance settlements')) {
            $query->whereHas('request', function ($q) use ($request) {
                $q->where(
                    'employee_id',
                    $request->user()->employee?->id
                );
            });
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $query->paginate(15),
        ]);
    }

    public function show(CashAdvanceSettlement $settlement)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $settlement->load([
                'request.employee',
                'items.category',
                'attachments',
                'approvalRequest.stepDecisions.approvalStep',
            ]),
        ]);
    }

    public function store(
        StoreCashAdvanceSettlementRequest $request,
    ) {
        $settlement = $this->settlementService->submit(
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Settlement berhasil diajukan',
            'data' => $settlement,
        ], 201);
    }

    public function decide(
        DecideCashAdvanceSettlementApprovalRequest $request,
        $decision,
    ) {
        $result = $this->approvalService->decide(
            $decision,
            $request->user(),
            $request->validated('action'),
            $request->validated('notes'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Keputusan settlement berhasil diproses',
            'data' => $result,
        ]);
    }
}