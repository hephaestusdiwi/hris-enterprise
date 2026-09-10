<?php

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Exports\BpjsDetailExport;
use App\Modules\Payroll\Exports\BpjsSummaryExport;
use App\Modules\Payroll\Services\PayrollBpjsReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class PayrollBpjsReportController extends Controller
{
    public function __construct(private PayrollBpjsReportService $reportService)
    {
    }

    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'period_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);
    }

    public function bpjsDetail(Request $request)
    {
        $filters = $this->validateFilters($request);
        $rows = $this->reportService->bpjsDetail($filters);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'data' => $rows->items(),
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    public function bpjsSummary(Request $request)
    {
        $filters = $this->validateFilters($request);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $this->reportService->bpjsSummary($filters)]);
    }

    public function exportBpjsDetailExcel(Request $request)
    {
        $filters = $this->validateFilters($request);
        $rows = $this->reportService->bpjsDetailAll($filters);
        $filename = "bpjs-detail-{$filters['period_year']}-{$filters['period_month']}.xlsx";

        return Excel::download(new BpjsDetailExport($rows), $filename);
    }

    public function exportBpjsSummaryExcel(Request $request)
    {
        $filters = $this->validateFilters($request);
        $summary = $this->reportService->bpjsSummary($filters);
        $filename = "bpjs-summary-{$filters['period_year']}-{$filters['period_month']}.xlsx";

        return Excel::download(new BpjsSummaryExport($summary, $filters), $filename);
    }

    public function exportBpjsDetailPdf(Request $request)
    {
        $filters = $this->validateFilters($request);
        $rows = $this->reportService->bpjsDetailAll($filters);
        $filename = "bpjs-detail-{$filters['period_year']}-{$filters['period_month']}.pdf";

        return Pdf::loadView('payroll-reports.bpjs-detail-pdf', ['rows' => $rows, 'filters' => $filters])
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    public function exportBpjsSummaryPdf(Request $request)
    {
        $filters = $this->validateFilters($request);
        $summary = $this->reportService->bpjsSummary($filters);
        $filename = "bpjs-summary-{$filters['period_year']}-{$filters['period_month']}.pdf";

        return Pdf::loadView('payroll-reports.bpjs-summary-pdf', ['summary' => $summary, 'filters' => $filters])
            ->download($filename);
    }
}
