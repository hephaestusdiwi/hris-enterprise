<?php

namespace App\Modules\Report\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Report\Exports\FinanceOutstandingExport;
use App\Modules\Report\Exports\FinanceSpendingExport;
use App\Modules\Report\Services\FinanceOutstandingReportService;
use App\Modules\Report\Services\FinanceSpendingReportService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class FinanceReportController extends Controller
{
    public function __construct(
        private FinanceSpendingReportService $spendingReportService,
        private FinanceOutstandingReportService $outstandingReportService,
    ) {
    }

    public function spending(Request $request)
    {
        $rows = $this->spendingReportService->spending($this->filters($request))->toArray();

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'rows' => $rows,
                'summary' => $this->spendingReportService->summary($rows),
            ],
        ]);
    }

    public function exportSpending(Request $request)
    {
        $rows = $this->spendingReportService->spending($this->filters($request));

        return Excel::download(
            new FinanceSpendingExport($rows),
            'finance-spending-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    public function outstanding(Request $request)
    {
        $rows = $this->outstandingReportService->outstanding($this->filters($request))->toArray();

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'rows' => $rows,
                'summary' => $this->outstandingReportService->summary($rows),
            ],
        ]);
    }

    public function exportOutstanding(Request $request)
    {
        $rows = $this->outstandingReportService->outstanding($this->filters($request));

        return Excel::download(
            new FinanceOutstandingExport($rows),
            'finance-outstanding-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /**
     * Dipakai bareng oleh semua endpoint report -- tiap Service cuma
     * membaca key filter yang relevan buat dirinya (yang lain diabaikan),
     * jadi aman di-generalize di sini daripada duplikat per-endpoint.
     *
     * @return array{company_id?: int, date_from?: string, date_to?: string, employee_id?: int, department_id?: int, category?: string, source_type?: string, source?: string, status?: string}
     */
    private function filters(Request $request): array
    {
        $filters = [];

        foreach (['company_id', 'employee_id', 'department_id'] as $key) {
            if ($request->filled($key)) {
                $filters[$key] = $request->integer($key);
            }
        }

        foreach (['date_from', 'date_to', 'category', 'source_type', 'source', 'status'] as $key) {
            if ($request->filled($key)) {
                $filters[$key] = $request->string($key)->toString();
            }
        }

        return $filters;
    }
}