<?php

namespace App\Modules\Reimbursement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reimbursement\Exceptions\ReimbursementValidationException;
use App\Modules\Reimbursement\Models\ReimbursementBalance;
use App\Modules\Reimbursement\Models\ReimbursementRequest as ReimbursementRequestModel;
use App\Modules\Reimbursement\Requests\CancelReimbursementRequest;
use App\Modules\Reimbursement\Requests\DisburseReimbursementRequest;
use App\Modules\Reimbursement\Requests\StoreReimbursementRequest;
use App\Modules\Reimbursement\Services\ReimbursementService;
use Illuminate\Http\Request;

class ReimbursementController extends Controller
{
    public function __construct(
        private ReimbursementService $reimbursementService
    ) {
    }

    public function index()
    {
        $reimbursements = ReimbursementRequestModel::with([
            'employee',
            'policy',
            'items.benefit'
        ])
            ->when(
                request('status'),
                fn ($q) => $q->where('status', request('status'))
            )
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $reimbursements,
        ]);
    }

    public function show(ReimbursementRequestModel $reimbursement)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $reimbursement->load([
                'employee.department',
                'policy',
                'balance',
                'items.benefit',
                'attachments',
                'approvalRequest.stepDecisions.approvalStep',
                'disbursedBy',
            ]),
        ]);
    }

    // ---- Self-service ----

    public function myBalances(Request $request)
    {
        $employee = $request->user()->employee;

        abort_if(
            ! $employee,
            422,
            'User ini tidak terhubung dengan data employee.'
        );

        $balances = ReimbursementBalance::with('policy.benefits')
            ->where('employee_id', $employee->id)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $balances,
        ]);
    }

    public function myReimbursements(Request $request)
    {
        $employee = $request->user()->employee;

        abort_if(
            ! $employee,
            422,
            'User ini tidak terhubung dengan data employee.'
        );

        $reimbursements = ReimbursementRequestModel::with([
            'policy',
            'items.benefit',
            'attachments'
        ])
            ->where('employee_id', $employee->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $reimbursements,
        ]);
    }

    public function myReimbursementShow(
        Request $request,
        ReimbursementRequestModel $reimbursement
    ) {
        $employee = $request->user()->employee;

        abort_if(
            ! $employee ||
            $reimbursement->employee_id !== $employee->id,
            403,
            'Anda tidak berhak melihat request ini.'
        );

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $reimbursement->load([
                'policy',
                'balance',
                'items.benefit',
                'attachments',
                'approvalRequest.stepDecisions.approvalStep'
            ]),
        ]);
    }

    public function store(StoreReimbursementRequest $request)
    {
        $employee = $request->user()->employee;

        abort_if(
            ! $employee,
            422,
            'User ini tidak terhubung dengan data employee.'
        );

        try {
            $reimbursement = $this->reimbursementService->submit(
                $employee,
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'Request berhasil diajukan',
                'data' => $reimbursement,
            ], 201);
        } catch (ReimbursementValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }

    // ---- Finance/HR action ----

    public function cancel(
        CancelReimbursementRequest $request,
        ReimbursementRequestModel $reimbursement
    ) {
        try {
            $reimbursement = $this->reimbursementService->cancel(
                $reimbursement,
                $request->validated('reason')
            );

            return response()->json([
                'success' => true,
                'message' => 'Request berhasil dibatalkan',
                'data' => $reimbursement,
            ]);
        } catch (ReimbursementValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }

    public function disburse(
        DisburseReimbursementRequest $request,
        ReimbursementRequestModel $reimbursement
    ) {
        try {
            $reimbursement = $this->reimbursementService->disburse(
                $reimbursement,
                $request->validated('disbursement_note'),
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Disbursement berhasil dicatat',
                'data' => $reimbursement,
            ]);
        } catch (ReimbursementValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }
}