<?php

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Services\AttendanceReportService;
use App\Modules\Payroll\Exceptions\PayrollValidationException;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Notifications\PayslipPublishedNotification;
use App\Modules\Pph21\Contracts\EmployeePtkpStatusResolverInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
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

    public function downloadPdf(
        Payslip $payslip,
        EmployeePtkpStatusResolverInterface $ptkpStatusResolver,
        AttendanceReportService $attendanceReportService,
    ) {
        $payslip->load([
            'employee.position',
            'employee.department',
            'employee.company',
            'payrollRun',
            'lines',
        ]);

        $ptkpStatus = $this->resolvePtkpStatus(
            $payslip,
            $ptkpStatusResolver
        );

        $attendanceSummary = $this->resolveAttendanceSummary(
            $payslip,
            $attendanceReportService
        );

        return Pdf::loadView(
            'payroll-reports.payslip-pdf',
            [
                'payslip' => $payslip,
                'ptkpStatus' => $ptkpStatus,
                'attendanceSummary' => $attendanceSummary,
            ]
        )->download(
            "payslip-{$payslip->employee->employee_number}-{$payslip->payrollRun->period_month}-{$payslip->payrollRun->period_year}.pdf"
        );
    }

    public function publish(Payslip $payslip)
    {
        try {
            $this->assertBelongsToCurrentRevision($payslip);

            $payslip->update(['is_published' => true]);

            $user = $payslip->employee?->user;

            if ($user) {
                try {
                    $user->notify(new PayslipPublishedNotification($payslip));
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Payslip berhasil dipublish',
                'data' => $payslip,
            ]);
        } catch (PayrollValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }

    public function unpublish(Payslip $payslip)
    {
        try {
            $this->assertBelongsToCurrentRevision($payslip);

            $payslip->update(['is_published' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Publish payslip dibatalkan',
                'data' => $payslip,
            ]);
        } catch (PayrollValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }

    private function assertBelongsToCurrentRevision(Payslip $payslip): void
    {
        $currentRevisionId = $payslip->payrollRun->currentRevision?->id;

        if ($payslip->payroll_run_revision_id !== $currentRevisionId) {
            throw new PayrollValidationException(
                'Payslip ini bukan milik revisi aktif — hanya payslip dari revisi terkini yang bisa di-publish/unpublish.'
            );
        }
    }

    public function myPayslips(Request $request)
    {
        $employee = $request->user()->employee;

        abort_if(
            ! $employee,
            422,
            'User ini tidak terhubung dengan data employee.'
        );

        $payslips = Payslip::where('employee_id', $employee->id)
            ->where('is_published', true)
            ->with('payrollRun')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $payslips,
        ]);
    }

    public function myPayslipShow(Request $request, Payslip $payslip)
    {
        $employee = $request->user()->employee;

        abort_if(
            ! $employee ||
            $payslip->employee_id !== $employee->id ||
            ! $payslip->is_published,
            403,
            'Payslip tidak ditemukan.'
        );

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $payslip->load(['lines', 'payrollRun']),
        ]);
    }

    public function myPayslipDownload(
        Request $request,
        Payslip $payslip,
        EmployeePtkpStatusResolverInterface $ptkpStatusResolver,
        AttendanceReportService $attendanceReportService,
    ) {
        $employee = $request->user()->employee;

        abort_if(
            ! $employee ||
            $payslip->employee_id !== $employee->id ||
            ! $payslip->is_published,
            403,
            'Payslip tidak ditemukan.'
        );

        $payslip->load([
            'employee.position',
            'employee.department',
            'employee.company',
            'payrollRun',
            'lines',
        ]);

        $ptkpStatus = $this->resolvePtkpStatus(
            $payslip,
            $ptkpStatusResolver
        );

        $attendanceSummary = $this->resolveAttendanceSummary(
            $payslip,
            $attendanceReportService
        );

        return Pdf::loadView(
            'payroll-reports.payslip-pdf',
            [
                'payslip' => $payslip,
                'ptkpStatus' => $ptkpStatus,
                'attendanceSummary' => $attendanceSummary,
            ]
        )->download(
            "payslip-{$payslip->payrollRun->period_month}-{$payslip->payrollRun->period_year}.pdf"
        );
    }

    private function resolvePtkpStatus(
        Payslip $payslip,
        EmployeePtkpStatusResolverInterface $resolver,
    ): ?string {
        $row = $resolver->resolveForTaxYear(
            (int) $payslip->employee_id,
            (int) $payslip->payrollRun->period_year,
        );

        return $row?->ptkp_status?->value;
    }

    private function resolveAttendanceSummary(
        Payslip $payslip,
        AttendanceReportService $attendanceReportService,
    ): array {
        $employee = $payslip->employee;

        /*
         * Match PayrollCalculationEngine:
         * periodStart = first day of payroll month
         * periodEnd = cutoff_date OR end of month
         */
        $referenceDate = Carbon::createFromDate(
            (int) $payslip->payrollRun->period_year,
            (int) $payslip->payrollRun->period_month,
            1
        );

        $periodStart = $referenceDate->copy()->startOfMonth();

        $periodEnd = $payslip->payrollRun->cutoff_date
            ?? $referenceDate->copy()->endOfMonth();

        /*
         * Reuse the same summary service used by payroll.
         */
        $rows = $attendanceReportService->summarize(
            collect([$employee]),
            $periodStart,
            $periodEnd
        );

        $summary = $rows[0] ?? [];

        /*
         * dailyRecap() is only used to derive holiday/dayoff counts.
         * It already exposes is_holiday, status, and shift.
         */
        $dailyRows = $attendanceReportService->dailyRecap(
            $employee,
            $periodStart,
            $periodEnd
        );

        $daily = collect($dailyRows);

        $summary['holiday_days'] = $daily
            ->where('is_holiday', true)
            ->count();

        $summary['dayoff_days'] = $daily
            ->filter(function (array $row) {
                return ! $row['is_holiday']
                    && $row['status'] === null
                    && $row['shift'] === null;
            })
            ->count();

        return $summary;
    }
}
