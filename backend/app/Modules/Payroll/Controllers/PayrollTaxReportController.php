<?php

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Exports\TaxDetailExport;
use App\Modules\Payroll\Exports\TaxSummaryExport;
use App\Modules\Payroll\Services\PayrollTaxReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class PayrollTaxReportController extends Controller
{
    public function __construct(private PayrollTaxReportService $reportService)
    {
    }

    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'period_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'employee_id' => ['nullable', 'exists:employees,id'],
        ]);
    }

    public function taxDetail(Request $request)
    {
        $filters = $this->validateFilters($request);
        $rows = $this->reportService->taxDetail($filters);

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

    public function taxSummary(Request $request)
    {
        $filters = $this->validateFilters($request);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $this->reportService->taxSummary($filters)]);
    }

    public function exportTaxDetailExcel(Request $request)
    {
        $filters = $this->validateFilters($request);
        $rows = $this->reportService->taxDetailAll($filters);
        $filename = "tax-detail-{$filters['period_year']}-{$filters['period_month']}.xlsx";

        return Excel::download(new TaxDetailExport($rows), $filename);
    }

    public function exportTaxSummaryExcel(Request $request)
    {
        $filters = $this->validateFilters($request);
        $summary = $this->reportService->taxSummary($filters);
        $filename = "tax-summary-{$filters['period_year']}-{$filters['period_month']}.xlsx";

        return Excel::download(new TaxSummaryExport($summary, $filters), $filename);
    }

    public function exportTaxDetailPdf(Request $request)
    {
        $filters = $this->validateFilters($request);
        $rows = $this->reportService->taxDetailAll($filters);
        $filename = "tax-detail-{$filters['period_year']}-{$filters['period_month']}.pdf";

        return Pdf::loadView('payroll-reports.tax-detail-pdf', ['rows' => $rows, 'filters' => $filters])
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    public function exportTaxSummaryPdf(Request $request)
    {
        $filters = $this->validateFilters($request);
        $summary = $this->reportService->taxSummary($filters);
        $filename = "tax-summary-{$filters['period_year']}-{$filters['period_month']}.pdf";

        return Pdf::loadView('payroll-reports.tax-summary-pdf', ['summary' => $summary, 'filters' => $filters])
            ->download($filename);
    }
}
