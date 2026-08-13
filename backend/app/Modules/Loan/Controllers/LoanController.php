<?php

namespace App\Modules\Loan\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Loan\Exceptions\LoanValidationException;
use App\Modules\Loan\Models\Loan;
use App\Modules\Loan\Requests\CancelLoanRequest;
use App\Modules\Loan\Requests\PreviewLoanRequest;
use App\Modules\Loan\Requests\StoreLoanRequest;
use App\Modules\Loan\Requests\UpdateLoanRequest;
use App\Modules\Loan\Services\LoanService;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function __construct(private LoanService $loanService)
    {
    }

    public function index(Request $request)
    {
        $loans = Loan::with(['employee.department'])
            ->when($request->query('employee_id'), fn ($q, $v) => $q->where('employee_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate(20);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $loans]);
    }

    public function show(Loan $loan)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $loan->load(['employee', 'installments', 'approvalRequest.stepDecisions.approvalStep', 'settlement']),
        ]);
    }

    public function myLoans(Request $request)
    {
        $employee = $request->user()->employee;

        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee.');

        $loans = Loan::with(['installments'])
            ->where('employee_id', $employee->id)
            ->latest()
            ->paginate(20);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $loans]);
    }

    public function myLoanShow(Request $request, Loan $loan)
    {
        $employee = $request->user()->employee;

        abort_if(! $employee || $loan->employee_id !== $employee->id, 403, 'Anda tidak berhak melihat loan ini.');

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $loan->load(['employee.department', 'installments', 'approvalRequest.stepDecisions.approvalStep']),
        ]);
    }

    public function preview(PreviewLoanRequest $request)
    {
        $plan = $this->loanService->calculateInstallmentPlan(
            (string) $request->validated('principal'),
            $request->validated('interest_rate') !== null ? (string) $request->validated('interest_rate') : null,
            (int) $request->validated('tenor'),
            $request->validated('interest_type'),
        );

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $plan]);
    }

    public function store(StoreLoanRequest $request)
    {
        $employee = Employee::findOrFail($request->validated('employee_id'));

        try {
            $loan = $this->loanService->create($employee, $request->validated(), $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Loan berhasil dibuat sebagai Draft',
                'data' => $loan,
            ], 201);
        } catch (LoanValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function update(UpdateLoanRequest $request, Loan $loan)
    {
        try {
            $loan = $this->loanService->update($loan, $request->validated());

            return response()->json(['success' => true, 'message' => 'Loan berhasil diperbarui', 'data' => $loan]);
        } catch (LoanValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function submit(Loan $loan)
    {
        try {
            $loan = $this->loanService->submit($loan);

            return response()->json(['success' => true, 'message' => 'Loan berhasil diajukan untuk approval', 'data' => $loan]);
        } catch (LoanValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function disburse(Loan $loan)
    {
        try {
            $loan = $this->loanService->disburse($loan);

            return response()->json([
                'success' => true,
                'message' => 'Loan berhasil dicairkan, jadwal cicilan sudah dibuat',
                'data' => $loan->load('installments'),
            ]);
        } catch (LoanValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function cancel(CancelLoanRequest $request, Loan $loan)
    {
        try {
            $loan = $this->loanService->cancel($loan, $request->validated('reason'));

            return response()->json(['success' => true, 'message' => 'Loan berhasil dibatalkan', 'data' => $loan]);
        } catch (LoanValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    /**
     * Business action eksplisit — Finance/HR yang trigger setelah employee
     * resign & masih ada outstanding loan. Lihat LoanService::settleForResignation().
     */
    public function settleResignation(Request $request, Loan $loan)
    {
        try {
            $loan = $this->loanService->settleForResignation($loan, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Final settlement berhasil dibuat, outstanding akan dipotong di payroll final period.',
                'data' => $loan->load(['installments', 'settlement']),
            ]);
        } catch (LoanValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }
}