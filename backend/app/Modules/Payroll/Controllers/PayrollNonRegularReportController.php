<?php

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Exports\NonRegularDetailExport;
use App\Modules\Payroll\Services\PayrollNonRegularReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class PayrollNonRegularReportController extends Controller
{
    public function __construct(private PayrollNonRegularReportService $reportService)
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
            'non_regular_payroll_component_id' => ['nullable', 'exists:non_regular_payroll_components,id'],
            'status' => ['nullable', 'string'],
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
        $filename = 'non-regular-payroll-detail-'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new NonRegularDetailExport($rows), $filename);
    }

    public function exportPdf(Request $request)
    {
        $filters = $this->validateFilters($request);
        $rows = $this->reportService->detailAll($filters);
        $filename = 'non-regular-payroll-detail-'.now()->format('Y-m-d').'.pdf';

        return Pdf::loadView('payroll-reports.non-regular-detail-pdf', ['rows' => $rows])
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }
}