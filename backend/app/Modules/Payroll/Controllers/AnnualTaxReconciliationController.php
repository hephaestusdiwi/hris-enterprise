<?php

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Exports\AnnualTaxReconciliationExport;
use App\Modules\Payroll\Services\EmployeeAnnualTaxReconciliationReportService;
use App\Modules\Pph21\Contracts\EmployeePtkpStatusResolverInterface;
use App\Modules\Pph21\Models\EmployeeTaxProfile;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AnnualTaxReconciliationController extends Controller
{
    public function __construct(
        private EmployeeAnnualTaxReconciliationReportService $reportService,
        private EmployeePtkpStatusResolverInterface $ptkpStatusResolver,
    ) {
    }

    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'tax_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'employee_id' => ['nullable', 'exists:employees,id'],
        ]);
    }

    /**
     * Recap seluruh employee per tahun pajak — rekap internal HR/Finance,
     * bahan cross-check saat input manual ke Coretax e-Bupot Unifikasi
     * (BUKAN file import Coretax yang tervalidasi — lihat catatan scope).
     */
    public function recap(Request $request)
    {
        $filters = $this->validateFilters($request);
        $rows = $this->reportService->detailAll($filters);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $rows->values()]);
    }

    public function exportRecapExcel(Request $request)
    {
        $filters = $this->validateFilters($request);
        $rows = $this->reportService->detailAll($filters);
        $filename = 'tax-annual-recap-'.$filters['tax_year'].'.xlsx';

        return Excel::download(new AnnualTaxReconciliationExport($rows), $filename);
    }

    /**
     * BPA1 (dulu 1721-A1) per employee per tahun pajak — dokumen buat
     * diberikan ke karyawan (kewajiban employer, terlepas dari Coretax).
     * TIDAK submit ke Coretax — murni generate PDF dari data yang sudah
     * dipersist di employee_annual_tax_reconciliations.
     */
    public function downloadBpa1(Request $request, Employee $employee)
    {
        $request->validate(['tax_year' => ['required', 'integer', 'min:2020', 'max:2100']]);
        $taxYear = (int) $request->query('tax_year');

        $reconciliation = $this->reportService->findCurrentForEmployeeYear($employee->id, $taxYear);

        if (! $reconciliation) {
            return response()->json([
                'success' => false,
                'message' => "Belum ada rekonsiliasi pajak tahunan untuk employee ini di tahun pajak {$taxYear}. Pastikan payroll run periode final (Desember/resign) sudah diproses.",
                'data' => null,
            ], 404);
        }

        $employee->load(['company', 'position']);
        $taxProfile = EmployeeTaxProfile::where('employee_id', $employee->id)->first();
        $ptkpStatus = $this->ptkpStatusResolver->resolveForTaxYear($employee->id, $taxYear);

        $filename = "bpa1-{$employee->employee_number}-{$taxYear}.pdf";

        return Pdf::loadView('payroll-reports.bpa1-pdf', [
            'employee' => $employee,
            'company' => $employee->company,
            'reconciliation' => $reconciliation,
            'taxProfile' => $taxProfile,
            'ptkpStatus' => $ptkpStatus,
            'taxYear' => $taxYear,
        ])->setPaper('a4', 'portrait')->download($filename);
    }
}
