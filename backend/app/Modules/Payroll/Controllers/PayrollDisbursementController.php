<?php

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Exceptions\PayrollValidationException;
use App\Modules\Payroll\Models\PayrollDisbursementBatch;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Requests\MarkDisbursementFailedRequest;
use App\Modules\Payroll\Services\PayrollDisbursementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class PayrollDisbursementController extends Controller
{
    public function __construct(private PayrollDisbursementService $disbursementService)
    {
    }

    public function index(PayrollRun $payrollRun)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $payrollRun->disbursementBatches()->with('revision')->get(),
        ]);
    }

    public function store(Request $request, PayrollRun $payrollRun)
    {
        try {
            $batch = $this->disbursementService->generate($payrollRun, $request->user());

            return response()->json(['success' => true, 'message' => 'File disbursement berhasil digenerate', 'data' => $batch], 201);
        } catch (PayrollValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function download(PayrollDisbursementBatch $disbursement)
    {
        $csv = $this->disbursementService->toCsv($disbursement);
        $filename = "disbursement-{$disbursement->payroll_run_id}-batch-{$disbursement->id}.csv";

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function markSent(Request $request, PayrollDisbursementBatch $disbursement)
    {
        try {
            $batch = $this->disbursementService->markSent($disbursement, $request->user());

            return response()->json(['success' => true, 'message' => 'Batch ditandai sudah dikirim ke bank', 'data' => $batch]);
        } catch (PayrollValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function markConfirmed(Request $request, PayrollDisbursementBatch $disbursement)
    {
        try {
            $batch = $this->disbursementService->markConfirmed($disbursement, $request->user());

            return response()->json(['success' => true, 'message' => 'Batch ditandai terkonfirmasi', 'data' => $batch]);
        } catch (PayrollValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function markFailed(MarkDisbursementFailedRequest $request, PayrollDisbursementBatch $disbursement)
    {
        try {
            $batch = $this->disbursementService->markFailed($disbursement, $request->user(), $request->validated('reason'));

            return response()->json(['success' => true, 'message' => 'Batch ditandai gagal', 'data' => $batch]);
        } catch (PayrollValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }
}
