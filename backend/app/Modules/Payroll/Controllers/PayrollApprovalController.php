<?php

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Exceptions\PayrollApprovalException;
use App\Modules\Payroll\Models\PayrollApprovalStepDecision;
use App\Modules\Payroll\Requests\DecidePayrollApprovalRequest;
use App\Modules\Payroll\Services\PayrollApprovalService;
use Illuminate\Http\Request;

class PayrollApprovalController extends Controller
{
    public function __construct(private PayrollApprovalService $approvalService)
    {
    }

    /**
     * Approver butuh info ringkas payroll SEBELUM mutusin — bukan cuma
     * periode/company, tapi jumlah employee, gross, BPJS, PPh21, net payroll
     * (lihat requirement Approval UX). Diambil dari payslip revisi aktif,
     * bukan query baru — reuse data yang sudah ada.
     */
    public function index(Request $request)
    {
        $decisions = $this->approvalService->pendingDecisionsForUser($request->user());

        $data = array_map(function ($decision) {
            $run = $decision->request->payrollRun;
            $payslips = $run->currentRevision?->payslips ?? collect();

            return [
                'id' => $decision->id,
                'sequence' => $decision->sequence,
                'approval_step' => $decision->approvalStep,
                'request' => [
                    'id' => $decision->request->id,
                    'payroll_run' => [
                        'id' => $run->id,
                        'period_year' => $run->period_year,
                        'period_month' => $run->period_month,
                        'company' => $run->company,
                    ],
                ],
                'summary' => [
                    'employee_count' => $payslips->count(),
                    'gross_payroll' => (string) $payslips->sum('gross_earning'),
                    'total_bpjs_employee' => (string) $payslips->sum('bpjs_employee_total'),
                    'total_bpjs_employer' => (string) $payslips->sum('bpjs_employer_total'),
                    'total_pph21' => (string) $payslips->sum('tax_amount'),
                    'total_loan_deduction' => (string) $payslips->sum('loan_deduction_total'),
                    'net_payroll' => (string) $payslips->sum('net_pay'),
                ],
            ];
        }, $decisions);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $data]);
    }

    public function decide(DecidePayrollApprovalRequest $request, PayrollApprovalStepDecision $decision)
    {
        try {
            $result = $this->approvalService->decide(
                $decision,
                $request->user(),
                $request->validated('action'),
                $request->validated('notes'),
            );

            return response()->json([
                'success' => true,
                'message' => $request->validated('action') === 'approve' ? 'Berhasil di-approve' : 'Berhasil ditolak',
                'data' => $result,
            ]);
        } catch (PayrollApprovalException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }
}