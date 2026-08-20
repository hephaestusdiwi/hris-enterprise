<?php

namespace App\Modules\Payroll\Services;

use App\Models\User;
use App\Modules\ApprovalFlow\DataTransferObjects\ApprovalScope;
use App\Modules\ApprovalFlow\Services\ApprovalFlowResolver;
use App\Modules\Attendance\Services\ApprovalStepApproverResolver;
use App\Modules\Payroll\Enums\PayrollApprovalRequestStatus;
use App\Modules\Payroll\Enums\PayrollApprovalStepDecisionStatus;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Exceptions\PayrollApprovalException;
use App\Modules\Payroll\Models\PayrollApprovalRequest;
use App\Modules\Payroll\Models\PayrollApprovalStepDecision;
use App\Modules\Payroll\Models\PayrollRun;

class PayrollApprovalService
{
    public function __construct(
        private ApprovalStepApproverResolver $resolver,
        private ApprovalFlowResolver $approvalFlowResolver,
    ) {
    }

    /**
     * PayrollRun bukan employee — scope-nya diambil langsung dari company_id
     * run itu sendiri, BUKAN dari employee yang submit. Konsekuensinya: tier
     * Assignment & JobLevel/Department di ApprovalFlowResolver otomatis skip
     * (scope tidak punya employeeId/jobLevelId/departmentId), langsung ke
     * Branch (kalau PayrollRun someday punya branch_id) atau Company-wide.
     */
    public function initiate(PayrollRun $payrollRun): void
    {
        $scope = new ApprovalScope(
            companyId: $payrollRun->company_id
        );

        $approvalFlow = $this->approvalFlowResolver->resolveForScope(
            $scope,
            'payroll'
        );

        if (! $approvalFlow) {
            $this->autoApprove($payrollRun);

            return;
        }

        $steps = $approvalFlow->steps()->where('is_active', true)->orderBy('sequence')->get();

        if ($steps->isEmpty()) {
            $this->autoApprove($payrollRun);

            return;
        }

        $request = PayrollApprovalRequest::create([
            'payroll_run_id' => $payrollRun->id,
            'approval_flow_id' => $approvalFlow->id,
            'status' => PayrollApprovalRequestStatus::Pending->value,
            'current_step_sequence' => $steps->first()->sequence,
            'requested_at' => now(),
        ]);

        foreach ($steps as $step) {
            PayrollApprovalStepDecision::create([
                'payroll_approval_request_id' => $request->id,
                'approval_step_id' => $step->id,
                'sequence' => $step->sequence,
                'status' => PayrollApprovalStepDecisionStatus::Pending->value,
            ]);
        }
    }

    public function autoApprove(PayrollRun $payrollRun): void
    {
        $this->applyApproval($payrollRun);
    }

    public function cancelApprovalIfAny(PayrollRun $payrollRun): void
    {
        $request = $payrollRun->approvalRequest;

        if ($request && $request->status === PayrollApprovalRequestStatus::Pending) {
            $request->update(['decided_at' => now()]);
        }
    }

    /**
     * Tidak ada subject Employee sama sekali di sini — kalau step-nya
     * ternyata approver_type=DirectManager (konfigurasi yang salah untuk
     * flow Payroll), resolver bakal balikin array kosong dan approval ini
     * ga akan pernah bisa diputuskan siapa pun. Itu perilaku yang disengaja
     * (lihat proposal arsitektur) — bukan bug, tapi sinyal HR salah
     * konfigurasi approval flow untuk Payroll.
     */
    public function decide(
        PayrollApprovalStepDecision $decision,
        User $actor,
        string $action,
        ?string $notes,
    ): PayrollApprovalRequest {
        $request = $decision->request;

        if ($request->payrollRun->status !== PayrollRunStatus::PendingApproval) {
            throw new PayrollApprovalException('Payroll run ini sudah tidak pending.');
        }

        if ($request->status !== PayrollApprovalRequestStatus::Pending) {
            throw new PayrollApprovalException('Request ini sudah tidak pending.');
        }

        if ($decision->sequence !== $request->current_step_sequence) {
            throw new PayrollApprovalException('Bukan giliran step ini untuk diputuskan.');
        }

        if ($decision->status !== PayrollApprovalStepDecisionStatus::Pending) {
            throw new PayrollApprovalException('Step ini sudah diputuskan sebelumnya.');
        }

        $eligibleUserIds = $this->resolver->resolveApproverUserIds($decision->approvalStep, null);

        if (! in_array($actor->id, $eligibleUserIds, true)) {
            throw new PayrollApprovalException('Anda tidak berwenang memutuskan approval ini.');
        }

        if ($action === 'reject') {
            $decision->update([
                'status' => PayrollApprovalStepDecisionStatus::Rejected->value,
                'decided_by_user_id' => $actor->id,
                'notes' => $notes,
                'decided_at' => now(),
            ]);

            $request->update(['status' => PayrollApprovalRequestStatus::Rejected->value, 'decided_at' => now()]);
            // Balik ke Processed (bukan Draft) — payslip yang sudah dihitung tetap ada,
            // HR tinggal recalculate/perbaiki lalu request approval lagi.
            $request->payrollRun->update(['status' => PayrollRunStatus::Processed->value]);

            return $request->fresh();
        }

        $decision->update([
            'status' => PayrollApprovalStepDecisionStatus::Approved->value,
            'decided_by_user_id' => $actor->id,
            'notes' => $notes,
            'decided_at' => now(),
        ]);

        $nextStep = PayrollApprovalStepDecision::where('payroll_approval_request_id', $request->id)
            ->where('sequence', '>', $decision->sequence)
            ->orderBy('sequence')
            ->first();

        if (! $nextStep) {
            $request->update(['status' => PayrollApprovalRequestStatus::Approved->value, 'decided_at' => now()]);
            $this->applyApproval($request->payrollRun);
        } else {
            $request->update(['current_step_sequence' => $nextStep->sequence]);
        }

        return $request->fresh();
    }

    /**
     * @return array<int, PayrollApprovalStepDecision>
     */
    public function pendingDecisionsForUser(User $user): array
    {
        $decisions = PayrollApprovalStepDecision::query()
            ->where('status', PayrollApprovalStepDecisionStatus::Pending->value)
            ->whereHas('request', fn ($query) => $query->where('status', PayrollApprovalRequestStatus::Pending->value))
            ->with(['approvalStep', 'request.payrollRun'])
            ->get()
            ->filter(fn (PayrollApprovalStepDecision $decision) => $decision->sequence === $decision->request->current_step_sequence)
            ->filter(fn (PayrollApprovalStepDecision $decision) => in_array(
                $user->id,
                $this->resolver->resolveApproverUserIds($decision->approvalStep, null),
                true,
            ));

        return $decisions->values()->all();
    }

    private function applyApproval(PayrollRun $payrollRun): void
    {
        $payrollRun->update(['status' => PayrollRunStatus::Approved->value, 'decided_at' => now()]);
    }
}