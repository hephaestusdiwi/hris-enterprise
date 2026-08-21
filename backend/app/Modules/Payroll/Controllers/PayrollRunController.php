<?php

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Exceptions\PayrollValidationException;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Requests\CancelPayrollRunRequest;
use App\Modules\Payroll\Requests\StorePayrollRunRequest;
use App\Modules\Payroll\Requests\UpdatePayrollRunParticipantsRequest;
use App\Modules\Payroll\Services\PayrollRunService;
use Illuminate\Http\Request;

class PayrollRunController extends Controller
{
    public function __construct(private PayrollRunService $payrollRunService)
    {
    }

    public function index(Request $request)
    {
        $runs = PayrollRun::with(['company', 'currentRevision.payslips:id,payroll_run_revision_id,net_pay'])
            ->withCount('participants')
            ->when($request->query('company_id'), fn ($q, $v) => $q->where('company_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('period_year'), fn ($q, $v) => $q->where('period_year', $v))
            ->when($request->query('period_month'), fn ($q, $v) => $q->where('period_month', $v))
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->paginate(20);

        // Summary per row (jumlah employee & total net payroll) dihitung di
        // backend, bukan di frontend — participants_count dari withCount,
        // total_net_payroll dari payslip revisi AKTIF (bukan seluruh revisi,
        // biar tidak double-count kalau sudah pernah direvisi berkali-kali).
        $runs->getCollection()->transform(function (PayrollRun $run) {
            $run->total_net_payroll = $run->currentRevision?->payslips->sum('net_pay') ?? 0;

            // PENTING: relation currentRevision() di-unset sebelum serialisasi.
            // Str::snake('currentRevision') == 'current_revision', collide
            // persis dengan kolom scalar current_revision (integer counter) —
            // Eloquent nge-overwrite attribute dengan relation kalau nama
            // key-nya sama pas toArray()/JSON encode. Nilai yang dibutuhkan
            // (total_net_payroll) udah diambil di baris atas, jadi aman
            // di-unset di sini supaya current_revision balik jadi integer.
            $run->unsetRelation('currentRevision');

            return $run;
        });

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $runs]);
    }

    public function show(PayrollRun $payrollRun)
    {
        $payrollRun->load([
            'participants',
            'currentRevision.payslips.employee',
            'currentRevision.payslips.lines',
            // Riwayat Revisi — SELURUH revisi (termasuk yang sudah usang),
            // dipakai buat tab "Riwayat Revisi" di Payroll History. Revisi
            // lama read-only, tidak pernah ditimpa.
            'revisions.payslips.employee',
            'revisions.payslips.lines',
            'approvalRequest.stepDecisions.approvalStep',
            // Riwayat Approval — SELURUH approval request (bukan cuma yang
            // aktif/terbaru), dipakai buat tab "Riwayat Revisi" juga.
            'approvalRequests.stepDecisions.approvalStep',
        ]);

        // PENTING: relation currentRevision() TIDAK BISA langsung ikut
        // toArray() di bawah nama aslinya. Str::snake('currentRevision') ==
        // 'current_revision', collide persis dengan kolom scalar
        // current_revision (integer counter) — Eloquent nge-overwrite
        // attribute dengan relation kalau nama key-nya sama pas
        // toArray()/JSON encode, jadi 'current_revision' di response akan
        // jadi object revisi (atau null) alih-alih integer. Di-expose di
        // bawah key terpisah (current_revision_data), relation di-unset
        // dari model supaya current_revision balik jadi integer yang benar.
        $currentRevisionData = $payrollRun->currentRevision;
        $payrollRun->unsetRelation('currentRevision');

        $data = $payrollRun->toArray();
        $data['current_revision_data'] = $currentRevisionData;

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $data]);
    }

    public function store(StorePayrollRunRequest $request)
    {
        try {
            $run = $this->payrollRunService->createDraft(
                $request->validated('company_id'),
                $request->validated('period_year'),
                $request->validated('period_month'),
                $request->validated('employee_ids'),
                $request->validated('cutoff_date'),
                $request->validated('payment_date'),
                $request->user(),
            );

            return response()->json(['success' => true, 'message' => 'Payroll run berhasil dibuat sebagai Draft', 'data' => $run], 201);
        } catch (PayrollValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function updateParticipants(UpdatePayrollRunParticipantsRequest $request, PayrollRun $payrollRun)
    {
        try {
            $run = $this->payrollRunService->syncParticipants($payrollRun, $request->validated('employee_ids'));

            return response()->json(['success' => true, 'message' => 'Peserta payroll run berhasil diperbarui', 'data' => $run]);
        } catch (PayrollValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function proceedPayslip(Request $request, PayrollRun $payrollRun)
    {
        try {
            $run = $this->payrollRunService->proceedPayslip($payrollRun, $request->user(), $request->input('note'));
            $revisionNumber = $run->current_revision;

            $run->load('currentRevision.payslips.employee');
            // Sama seperti show()/index() — hindari collision current_revision
            // (scalar) vs relation currentRevision saat toArray().
            $currentRevisionData = $run->currentRevision;
            $run->unsetRelation('currentRevision');
            $data = $run->toArray();
            $data['current_revision_data'] = $currentRevisionData;

            return response()->json([
                'success' => true,
                'message' => 'Payslip berhasil di-generate (revisi ke-' . $revisionNumber . ')',
                'data' => $data,
            ]);
        } catch (PayrollValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function requestApproval(PayrollRun $payrollRun)
    {
        try {
            $run = $this->payrollRunService->requestApproval($payrollRun);

            return response()->json(['success' => true, 'message' => 'Permintaan approval Lock berhasil diajukan', 'data' => $run]);
        } catch (PayrollValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function lock(Request $request, PayrollRun $payrollRun)
    {
        try {
            $run = $this->payrollRunService->lock($payrollRun, $request->user());

            return response()->json(['success' => true, 'message' => 'Payroll run berhasil di-Lock. Data periode ini sudah final.', 'data' => $run]);
        } catch (PayrollValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function publish(Request $request, PayrollRun $payrollRun)
    {
        try {
            $run = $this->payrollRunService->publish($payrollRun, $request->user());

            return response()->json(['success' => true, 'message' => 'Payslip berhasil dipublish, karyawan bisa akses via ESS', 'data' => $run]);
        } catch (PayrollValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function unpublish(PayrollRun $payrollRun)
    {
        $run = $this->payrollRunService->unpublish($payrollRun);

        return response()->json(['success' => true, 'message' => 'Publish payslip dibatalkan', 'data' => $run]);
    }

    public function cancel(CancelPayrollRunRequest $request, PayrollRun $payrollRun)
    {
        try {
            $run = $this->payrollRunService->cancel($payrollRun, $request->validated('reason'));

            return response()->json(['success' => true, 'message' => 'Payroll run berhasil dibatalkan', 'data' => $run]);
        } catch (PayrollValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }
}