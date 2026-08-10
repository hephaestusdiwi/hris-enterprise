<?php

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
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
        $payslip->update(['is_published' => true]);

        return response()->json(['success' => true, 'message' => 'Payslip berhasil dipublish', 'data' => $payslip]);
    }

    public function unpublish(Payslip $payslip)
    {
        $payslip->update(['is_published' => false]);

        return response()->json(['success' => true, 'message' => 'Publish payslip dibatalkan', 'data' => $payslip]);
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