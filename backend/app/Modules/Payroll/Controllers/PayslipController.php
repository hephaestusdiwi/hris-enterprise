<?php

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Exceptions\PayrollValidationException;
use App\Modules\Payroll\Models\Payslip;
use Illuminate\Http\Request;

class PayslipController extends Controller
{
    public function show(Payslip $payslip)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $payslip->load(['employee', 'lines', 'payrollRun']),
        ]);
    }

    public function publish(Payslip $payslip)
    {
        try {
            $this->assertBelongsToCurrentRevision($payslip);

            $payslip->update(['is_published' => true]);

            return response()->json(['success' => true, 'message' => 'Payslip berhasil dipublish', 'data' => $payslip]);
        } catch (PayrollValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function unpublish(Payslip $payslip)
    {
        try {
            $this->assertBelongsToCurrentRevision($payslip);

            $payslip->update(['is_published' => false]);

            return response()->json(['success' => true, 'message' => 'Publish payslip dibatalkan', 'data' => $payslip]);
        } catch (PayrollValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    /**
     * Payslip cuma boleh di-publish/unpublish kalau dia milik revisi AKTIF
     * (current_revision) payroll run induknya. Tanpa guard ini, endpoint per-
     * payslip ini bisa memutasi is_published pada payslip dari revisi lama
     * yang sudah usang (superseded oleh recalculate) — beda dengan
     * PayrollRunService::publish()/unpublish() yang sudah scoped eksplisit ke
     * currentRevision(), endpoint ini menerima Payslip apa pun lewat route
     * model binding tanpa scoping. Lihat AUDIT PAYROLL — REVISION
     * IMMUTABILITY (HIGH finding).
     */
    private function assertBelongsToCurrentRevision(Payslip $payslip): void
    {
        $currentRevisionId = $payslip->payrollRun->currentRevision?->id;

        if ($payslip->payroll_run_revision_id !== $currentRevisionId) {
            throw new PayrollValidationException('Payslip ini bukan milik revisi aktif — hanya payslip dari revisi terkini yang bisa di-publish/unpublish.');
        }
    }

    public function myPayslips(Request $request)
    {
        $employee = $request->user()->employee;

        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee.');

        $payslips = Payslip::where('employee_id', $employee->id)
            ->where('is_published', true)
            ->with('payrollRun')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $payslips]);
    }

    public function myPayslipShow(Request $request, Payslip $payslip)
    {
        $employee = $request->user()->employee;

        abort_if(! $employee || $payslip->employee_id !== $employee->id || ! $payslip->is_published, 403, 'Payslip tidak ditemukan.');

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $payslip->load(['lines', 'payrollRun'])]);
    }
}