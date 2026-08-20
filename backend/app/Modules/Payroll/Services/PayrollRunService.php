<?php

namespace App\Modules\Payroll\Services;

use App\Models\User;
use App\Modules\EmployeeAllowance\Models\EmployeeAllowance;
use App\Modules\EmployeeDeduction\Models\EmployeeDeduction;
use App\Modules\Loan\Enums\LoanInstallmentStatus;
use App\Modules\Loan\Models\LoanInstallment;
use App\Modules\Loan\Services\LoanService;
use App\Modules\Payroll\Contracts\PayrollCalculationEngineInterface;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Exceptions\PayrollValidationException;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\PayslipLine;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\PayrollRunRevision;
use Illuminate\Support\Facades\DB;

class PayrollRunService
{
    public function __construct(
        private PayrollApprovalService $approvalService,
        private PayrollCalculationEngineInterface $calculationEngine,
        private LoanService $loanService,
    ) {
    }

    /**
     * @param  array<int, int>  $employeeIds
     */
    public function createDraft(
        int $companyId,
        int $periodYear,
        int $periodMonth,
        array $employeeIds,
        ?string $cutoffDate,
        ?string $paymentDate,
        ?User $actor,
    ): PayrollRun {
        return DB::transaction(function () use ($companyId, $periodYear, $periodMonth, $employeeIds, $cutoffDate, $paymentDate, $actor) {
            $run = PayrollRun::create([
                'company_id' => $companyId,
                'period_year' => $periodYear,
                'period_month' => $periodMonth,
                'cutoff_date' => $cutoffDate,
                'payment_date' => $paymentDate,
                'status' => PayrollRunStatus::Draft->value,
                'created_by_user_id' => $actor?->id,
            ]);

            $run->participants()->sync($employeeIds);

            return $run->fresh();
        });
    }

    public function syncParticipants(PayrollRun $run, array $employeeIds): PayrollRun
    {
        if (! $run->isEditableParticipants()) {
            throw new PayrollValidationException('Peserta cuma bisa diubah selama status Draft.');
        }

        $run->participants()->sync($employeeIds);

        return $run->fresh();
    }

    /**
     * Kalkulasi payslip — dipakai baik buat generate pertama kali (dari Draft)
     * MAUPUN recalculate (dari Processed/PendingApproval/Approved). TIDAK
     * digating status approval sama sekali — HR bebas hitung ulang kapan pun
     * sebelum Lock, sesuai behavior Overview/Detail Talenta.
     *
     * Kalau dipanggil saat status PendingApproval/Approved, approval yang
     * berjalan untuk revisi lama otomatis di-invalidate (karena angkanya
     * berubah) — status turun balik ke Processed, HR harus request approval
     * lagi buat revisi baru sebelum bisa Lock.
     */
    public function proceedPayslip(PayrollRun $run, ?User $actor, ?string $note = null): PayrollRun
    {
        if (in_array($run->status, [PayrollRunStatus::Locked, PayrollRunStatus::Cancelled], true)) {
            throw new PayrollValidationException('Payroll run yang sudah Locked/Cancelled tidak bisa dihitung ulang.');
        }

        if ($run->participants()->count() === 0) {
            throw new PayrollValidationException('Pilih minimal 1 employee sebelum menghitung payroll.');
        }

        return DB::transaction(function () use ($run, $actor, $note) {
            if (in_array($run->status, [PayrollRunStatus::PendingApproval, PayrollRunStatus::Approved], true)) {
                $this->approvalService->cancelApprovalIfAny($run);
            }

            $drafts = $this->calculationEngine->calculateDraftsForRun($run);

            $revisionNumber = $run->current_revision + 1;

            $revision = PayrollRunRevision::create([
                'payroll_run_id' => $run->id,
                'revision_number' => $revisionNumber,
                'calculated_at' => now(),
                'calculated_by_user_id' => $actor?->id,
                'note' => $note,
            ]);

            foreach ($drafts as $employeeId => $draft) {
                $payslip = Payslip::create([
                    'payroll_run_id' => $run->id,
                    'payroll_run_revision_id' => $revision->id,
                    'employee_id' => $employeeId,
                    'gross_earning' => $draft->grossEarning,
                    'structural_deduction' => $draft->structuralDeduction,
                    'manual_deduction_total' => $draft->manualDeductionTotal,
                    'bpjs_employee_total' => $draft->bpjsEmployeeTotal,
                    'bpjs_employer_total' => $draft->bpjsEmployerTotal,
                    'tax_amount' => $draft->taxAmount,
                    'loan_deduction_total' => $draft->loanDeductionTotal,
                    'net_pay' => $draft->netPay,
                ]);

                foreach ($draft->lines as $line) {
                    PayslipLine::create([
                        'payslip_id' => $payslip->id,
                        'type' => $line->type->value,
                        'source' => $line->source->value,
                        'label' => $line->label,
                        'amount' => $line->amount,
                        'reference_id' => $line->referenceId,
                    ]);
                }
            }

            $run->update([
                'current_revision' => $revisionNumber,
                'status' => PayrollRunStatus::Processed->value,
                'processed_at' => now(),
            ]);

            return $run->fresh();
        });
    }

    /**
     * Gerbang akses sebelum Lock — mirror "apply for access to lock payroll"
     * Talenta. Bukan approval terhadap kalkulasi (itu sudah selesai di
     * proceedPayslip), tapi approval terhadap AKSI Lock itu sendiri.
     */
    public function requestApproval(PayrollRun $run): PayrollRun
    {
        if ($run->status !== PayrollRunStatus::Processed) {
            throw new PayrollValidationException('Payroll run harus berstatus Processed (sudah ada payslip) sebelum minta approval Lock.');
        }

        DB::transaction(function () use ($run) {
            $run->update(['status' => PayrollRunStatus::PendingApproval->value, 'requested_at' => now()]);
            $this->approvalService->initiate($run);
        });

        return $run->fresh();
    }

    /**
     * Finalisasi. EmployeeAllowance/Deduction/LoanInstallment yang kepakai
     * revision aktif baru ditandai consumed DI SINI — bukan pas calculate,
     * supaya recalculate sebelum Lock tidak pernah nyentuh data sumbernya.
     */
    public function lock(PayrollRun $run, User $actor): PayrollRun
    {
        if ($run->status !== PayrollRunStatus::Approved) {
            throw new PayrollValidationException('Hanya payroll run berstatus Approved yang bisa di-Lock.');
        }

        return DB::transaction(function () use ($run, $actor) {
            $employeeIds = $run->participants()->pluck('employees.id');

            EmployeeAllowance::whereIn('employee_id', $employeeIds)
                ->where('status', 'ready')
                ->where('payroll_period_year', $run->period_year)
                ->where('payroll_period_month', $run->period_month)
                ->update(['status' => 'processed', 'processed_at' => now()]);

            EmployeeDeduction::whereIn('employee_id', $employeeIds)
                ->where('status', 'ready')
                ->where('payroll_period_year', $run->period_year)
                ->where('payroll_period_month', $run->period_month)
                ->update(['status' => 'processed', 'processed_at' => now()]);

            $dueInstallments = LoanInstallment::whereHas('loan', fn ($q) => $q->whereIn('employee_id', $employeeIds))
                ->where('status', LoanInstallmentStatus::Scheduled->value)
                ->where('payroll_period_year', $run->period_year)
                ->where('payroll_period_month', $run->period_month)
                ->get();

            foreach ($dueInstallments as $installment) {
                $this->loanService->markInstallmentPaid($installment);
            }

            $run->update([
                'status' => PayrollRunStatus::Locked->value,
                'locked_at' => now(),
                'locked_by_user_id' => $actor->id,
            ]);

            return $run->fresh();
        });
    }

    public function publish(PayrollRun $run, User $actor): PayrollRun
    {
        if ($run->status !== PayrollRunStatus::Locked) {
            throw new PayrollValidationException('Hanya payroll run berstatus Locked yang bisa di-Publish.');
        }

        DB::transaction(function () use ($run, $actor) {
            $run->currentRevision?->payslips()->update(['is_published' => true]);
            $run->update(['published_at' => now(), 'published_by_user_id' => $actor->id]);
        });

        return $run->fresh();
    }

    public function unpublish(PayrollRun $run): PayrollRun
    {
        DB::transaction(function () use ($run) {
            $run->currentRevision?->payslips()->update(['is_published' => false]);
            $run->update(['published_at' => null, 'published_by_user_id' => null]);
        });

        return $run->fresh();
    }

    public function cancel(PayrollRun $run, string $reason): PayrollRun
    {
        if ($run->status === PayrollRunStatus::Locked) {
            throw new PayrollValidationException('Payroll run yang sudah Locked tidak bisa dibatalkan.');
        }

        $this->approvalService->cancelApprovalIfAny($run);
        $run->update(['status' => PayrollRunStatus::Cancelled->value, 'cancelled_at' => now(), 'cancel_reason' => $reason]);

        return $run->fresh();
    }
}