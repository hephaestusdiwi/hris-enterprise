<?php

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Exceptions\PayrollValidationException;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Requests\CancelPayrollRunRequest;
use App\Modules\Payroll\Requests\StorePayrollRunRequest;
use App\Modules\Payroll\Requests\UpdatePayrollRunParticipantsRequest;
use App\Modules\Payroll\Services\PayrollRunService;
use Illuminate\Http\Request;

class PayrollRunController extends Controller
{
    public function __construct(private PayrollRunService $payrollRunService)
    {
    }

    public function index(Request $request)
    {
        $runs = PayrollRun::with('company')
            ->when($request->query('company_id'), fn ($q, $v) => $q->where('company_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->paginate(20);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $runs]);
    }

    public function show(PayrollRun $payrollRun)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $payrollRun->load([
                'participants',
                'currentRevision.payslips.employee',
                'currentRevision.payslips.lines',
                'approvalRequest.stepDecisions.approvalStep',
            ]),
        ]);
    }

    public function store(StorePayrollRunRequest $request)
    {
        try {
            $run = $this->payrollRunService->createDraft(
                $request->validated('company_id'),
                $request->validated('period_year'),
                $request->validated('period_month'),
                $request->validated('employee_ids'),
                $request->validated('cutoff_date'),
                $request->validated('payment_date'),
                $request->user(),
            );

            return response()->json(['success' => true, 'message' => 'Payroll run berhasil dibuat sebagai Draft', 'data' => $run], 201);
        } catch (PayrollValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function updateParticipants(UpdatePayrollRunParticipantsRequest $request, PayrollRun $payrollRun)
    {
        try {
            $run = $this->payrollRunService->syncParticipants($payrollRun, $request->validated('employee_ids'));

            return response()->json(['success' => true, 'message' => 'Peserta payroll run berhasil diperbarui', 'data' => $run]);
        } catch (PayrollValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function proceedPayslip(Request $request, PayrollRun $payrollRun)
    {
        try {
            $run = $this->payrollRunService->proceedPayslip($payrollRun, $request->user(), $request->input('note'));

            return response()->json([
                'success' => true,
                'message' => 'Payslip berhasil di-generate (revisi ke-' . $run->current_revision . ')',
                'data' => $run->load('currentRevision.payslips.employee'),
            ]);
        } catch (PayrollValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function requestApproval(PayrollRun $payrollRun)
    {
        try {
            $run = $this->payrollRunService->requestApproval($payrollRun);

            return response()->json(['success' => true, 'message' => 'Permintaan approval Lock berhasil diajukan', 'data' => $run]);
        } catch (PayrollValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function lock(Request $request, PayrollRun $payrollRun)
    {
        try {
            $run = $this->payrollRunService->lock($payrollRun, $request->user());

            return response()->json(['success' => true, 'message' => 'Payroll run berhasil di-Lock. Data periode ini sudah final.', 'data' => $run]);
        } catch (PayrollValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function publish(Request $request, PayrollRun $payrollRun)
    {
        try {
            $run = $this->payrollRunService->publish($payrollRun, $request->user());

            return response()->json(['success' => true, 'message' => 'Payslip berhasil dipublish, karyawan bisa akses via ESS', 'data' => $run]);
        } catch (PayrollValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function unpublish(PayrollRun $payrollRun)
    {
        $run = $this->payrollRunService->unpublish($payrollRun);

        return response()->json(['success' => true, 'message' => 'Publish payslip dibatalkan', 'data' => $run]);
    }

    public function cancel(CancelPayrollRunRequest $request, PayrollRun $payrollRun)
    {
        try {
            $run = $this->payrollRunService->cancel($payrollRun, $request->validated('reason'));

            return response()->json(['success' => true, 'message' => 'Payroll run berhasil dibatalkan', 'data' => $run]);
        } catch (PayrollValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }
}