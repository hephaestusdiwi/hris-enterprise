<?php

namespace App\Modules\Reimbursement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Reimbursement\Exceptions\ReimbursementValidationException;
use App\Modules\Reimbursement\Models\ReimbursementBalance;
use App\Modules\Reimbursement\Models\ReimbursementPolicy;
use App\Modules\Reimbursement\Requests\AssignReimbursementBalanceRequest;
use App\Modules\Reimbursement\Requests\StopReimbursementBalanceRequest;
use App\Modules\Reimbursement\Services\ReimbursementBalanceService;

class ReimbursementBalanceController extends Controller
{
    public function __construct(private ReimbursementBalanceService $balanceService)
    {
    }

    public function index()
    {
        $balances = ReimbursementBalance::with(['employee', 'policy'])
            ->when(
                request('employee_id'),
                fn ($q) => $q->where('employee_id', request('employee_id'))
            )
            ->when(
                request('reimbursement_policy_id'),
                fn ($q) => $q->where(
                    'reimbursement_policy_id',
                    request('reimbursement_policy_id')
                )
            )
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $balances,
        ]);
    }

    public function store(AssignReimbursementBalanceRequest $request)
    {
        $employee = Employee::findOrFail(
            $request->validated('employee_id')
        );

        $policy = ReimbursementPolicy::findOrFail(
            $request->validated('reimbursement_policy_id')
        );

        try {
            $balance = $this->balanceService->assign(
                $employee,
                $policy,
                $request->validated(),
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Balance berhasil di-assign',
                'data' => $balance,
            ], 201);
        } catch (ReimbursementValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }

    public function stop(
        StopReimbursementBalanceRequest $request,
        ReimbursementBalance $reimbursementBalance
    ) {
        try {
            $balance = $this->balanceService->stop(
                $reimbursementBalance,
                $request->validated('reason')
            );

            return response()->json([
                'success' => true,
                'message' => 'Balance berhasil dihentikan',
                'data' => $balance,
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