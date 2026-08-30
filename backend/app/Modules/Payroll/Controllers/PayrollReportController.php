<?php

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Exports\SalaryDetailExport;
use App\Modules\Payroll\Exports\SalarySummaryExport;
use App\Modules\Payroll\Services\PayrollReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class PayrollReportController extends Controller
{
    public function __construct(private PayrollReportService $reportService)
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

    public function salaryDetail(Request $request)
    {
        $filters = $this->validateFilters($request);
        $rows = $this->reportService->salaryDetail($filters);

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

    public function salarySummary(Request $request)
    {
        $filters = $this->validateFilters($request);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $this->reportService->salarySummary($filters)]);
    }

    public function exportSalaryDetailExcel(Request $request)
    {
        $filters = $this->validateFilters($request);
        $rows = $this->reportService->salaryDetailAll($filters);
        $filename = "salary-detail-{$filters['period_year']}-{$filters['period_month']}.xlsx";

        return Excel::download(new SalaryDetailExport($rows), $filename);
    }

    public function exportSalarySummaryExcel(Request $request)
    {
        $filters = $this->validateFilters($request);
        $summary = $this->reportService->salarySummary($filters);
        $filename = "salary-summary-{$filters['period_year']}-{$filters['period_month']}.xlsx";

        return Excel::download(new SalarySummaryExport($summary, $filters), $filename);
    }

    public function exportSalaryDetailPdf(Request $request)
    {
        $filters = $this->validateFilters($request);
        $rows = $this->reportService->salaryDetailAll($filters);
        $filename = "salary-detail-{$filters['period_year']}-{$filters['period_month']}.pdf";

        return Pdf::loadView('payroll-reports.salary-detail-pdf', ['rows' => $rows, 'filters' => $filters])
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    public function exportSalarySummaryPdf(Request $request)
    {
        $filters = $this->validateFilters($request);
        $summary = $this->reportService->salarySummary($filters);
        $filename = "salary-summary-{$filters['period_year']}-{$filters['period_month']}.pdf";

        return Pdf::loadView('payroll-reports.salary-summary-pdf', ['summary' => $summary, 'filters' => $filters])
            ->download($filename);
    }
}
