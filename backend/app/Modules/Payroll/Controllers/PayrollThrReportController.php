<?php

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Exports\ThrDetailExport;
use App\Modules\Payroll\Services\PayrollThrReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class PayrollThrReportController extends Controller
{
    public function __construct(private PayrollThrReportService $reportService)
    {
    }

    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'payroll_run_id' => ['nullable', 'exists:payroll_runs,id'],
            'period_year' => ['required_without:payroll_run_id', 'nullable', 'integer', 'min:2020', 'max:2100'],
            'period_month' => ['required_without:payroll_run_id', 'nullable', 'integer', 'min:1', 'max:12'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'employee_id' => ['nullable', 'exists:employees,id'],
        ]);
    }

    public function detail(Request $request)
    {
        $filters = $this->validateFilters($request);
        $rows = $this->reportService->detailAll($filters);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $rows->values()]);
    }

    public function exportExcel(Request $request)
    {
        $filters = $this->validateFilters($request);
        $rows = $this->reportService->detailAll($filters);
        $filename = 'thr-detail-'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new ThrDetailExport($rows), $filename);
    }

    public function exportPdf(Request $request)
    {
        $filters = $this->validateFilters($request);
        $rows = $this->reportService->detailAll($filters);
        $filename = 'thr-detail-'.now()->format('Y-m-d').'.pdf';

        return Pdf::loadView('payroll-reports.thr-detail-pdf', ['rows' => $rows])
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }
}