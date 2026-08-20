<?php

namespace App\Modules\HiringRequisition\Services;

use App\Models\User;
use App\Modules\ApprovalFlow\Services\ApprovalFlowResolver;
use App\Modules\Attendance\Services\ApprovalStepApproverResolver;
use App\Modules\Employee\Models\Employee;
use App\Modules\HiringRequisition\Enums\HiringRequisitionApprovalRequestStatus;
use App\Modules\HiringRequisition\Enums\HiringRequisitionApprovalStepDecisionStatus;
use App\Modules\HiringRequisition\Enums\HiringRequisitionStatus;
use App\Modules\HiringRequisition\Exceptions\HiringRequisitionApprovalException;
use App\Modules\HiringRequisition\Exceptions\HiringRequisitionValidationException;
use App\Modules\HiringRequisition\Models\HiringRequisition;
use App\Modules\HiringRequisition\Models\HiringRequisitionApprovalRequest;
use App\Modules\HiringRequisition\Models\HiringRequisitionApprovalStepDecision;

class HiringRequisitionApprovalService
{
    public function __construct(
        private ApprovalStepApproverResolver $resolver,
        private ApprovalFlowResolver $approvalFlowResolver,
    ) {
    }

    public function initiate(HiringRequisition $hiringRequisition, Employee $requestedBy): void
    {
        $approvalFlow = $this->approvalFlowResolver->resolveFor(
            $requestedBy,
            'hiring_requisition'
        );

        if (! $approvalFlow) {
            // Beda dengan Leave: headcount TIDAK auto-approve kalau tidak ada Approval Flow.
            // Requisition diblokir sampai admin men-setup Approval Flow yang berlaku.
            throw new HiringRequisitionValidationException(
                'Tidak ada Approval Flow yang berlaku untuk pengajuan ini. Hubungi admin untuk konfigurasi Approval Flow terlebih dahulu.'
            );
        }

        $steps = $approvalFlow->steps()->where('is_active', true)->orderBy('sequence')->get();

        if ($steps->isEmpty()) {
            throw new HiringRequisitionValidationException(
                'Approval Flow yang berlaku tidak memiliki step aktif. Hubungi admin untuk konfigurasi Approval Step.'
            );
        }

        $request = HiringRequisitionApprovalRequest::create([
            'hiring_requisition_id' => $hiringRequisition->id,
            'employee_id' => $requestedBy->id,
            'approval_flow_id' => $approvalFlow->id,
            'status' => HiringRequisitionApprovalRequestStatus::Pending->value,
            'current_step_sequence' => $steps->first()->sequence,
            'requested_at' => now(),
        ]);

        foreach ($steps as $step) {
            HiringRequisitionApprovalStepDecision::create([
                'hiring_requisition_approval_request_id' => $request->id,
                'approval_step_id' => $step->id,
                'sequence' => $step->sequence,
                'status' => HiringRequisitionApprovalStepDecisionStatus::Pending->value,
            ]);
        }
    }

    public function cancelApprovalIfAny(HiringRequisition $hiringRequisition): void
    {
        $request = $hiringRequisition->approvalRequest;

        if ($request && $request->status === HiringRequisitionApprovalRequestStatus::Pending) {
            $request->update(['decided_at' => now()]);
        }
    }

    public function decide(
        HiringRequisitionApprovalStepDecision $decision,
        User $actor,
        string $action,
        ?string $notes,
    ): HiringRequisitionApprovalRequest {
        $request = $decision->request;

        if ($request->hiringRequisition->status !== HiringRequisitionStatus::Pending) {
            throw new HiringRequisitionApprovalException('Hiring requisition ini sudah tidak pending (mungkin sudah dibatalkan).');
        }

        if ($request->status !== HiringRequisitionApprovalRequestStatus::Pending) {
            throw new HiringRequisitionApprovalException('Request ini sudah tidak pending.');
        }

        if ($decision->sequence !== $request->current_step_sequence) {
            throw new HiringRequisitionApprovalException('Bukan giliran step ini untuk diputuskan.');
        }

        if ($decision->status !== HiringRequisitionApprovalStepDecisionStatus::Pending) {
            throw new HiringRequisitionApprovalException('Step ini sudah diputuskan sebelumnya.');
        }

        $eligibleUserIds = $this->resolver->resolveApproverUserIds($decision->approvalStep, $request->employee);

        if (! in_array($actor->id, $eligibleUserIds, true)) {
            throw new HiringRequisitionApprovalException('Anda tidak berwenang memutuskan approval ini.');
        }

        if ($action === 'reject') {
            $decision->update([
                'status' => HiringRequisitionApprovalStepDecisionStatus::Rejected->value,
                'decided_by_user_id' => $actor->id,
                'notes' => $notes,
                'decided_at' => now(),
            ]);

            $request->update([
                'status' => HiringRequisitionApprovalRequestStatus::Rejected->value,
                'decided_at' => now(),
            ]);

            $request->hiringRequisition->update([
                'status' => HiringRequisitionStatus::Rejected->value,
                'decided_at' => now(),
            ]);

            return $request->fresh();
        }

        $decision->update([
            'status' => HiringRequisitionApprovalStepDecisionStatus::Approved->value,
            'decided_by_user_id' => $actor->id,
            'notes' => $notes,
            'decided_at' => now(),
        ]);

        $nextStep = HiringRequisitionApprovalStepDecision::where('hiring_requisition_approval_request_id', $request->id)
            ->where('sequence', '>', $decision->sequence)
            ->orderBy('sequence')
            ->first();

        if (! $nextStep) {
            $request->update([
                'status' => HiringRequisitionApprovalRequestStatus::Approved->value,
                'decided_at' => now(),
            ]);

            $this->applyApproval($request->hiringRequisition);
        } else {
            $request->update(['current_step_sequence' => $nextStep->sequence]);
        }

        return $request->fresh();
    }

    /**
     * @return array<int, HiringRequisitionApprovalStepDecision>
     */
    public function pendingDecisionsForUser(User $user): array
    {
        $decisions = HiringRequisitionApprovalStepDecision::query()
            ->where('status', HiringRequisitionApprovalStepDecisionStatus::Pending->value)
            ->whereHas('request', fn ($query) => $query->where('status', HiringRequisitionApprovalRequestStatus::Pending->value))
            ->with(['approvalStep', 'request.employee', 'request.hiringRequisition.position'])
            ->get()
            ->filter(fn (HiringRequisitionApprovalStepDecision $decision) => $decision->sequence === $decision->request->current_step_sequence)
            ->filter(function (HiringRequisitionApprovalStepDecision $decision) use ($user) {
                $eligibleUserIds = $this->resolver->resolveApproverUserIds($decision->approvalStep, $decision->request->employee);

                return in_array($user->id, $eligibleUserIds, true);
            });

        return $decisions->values()->all();
    }

    private function applyApproval(HiringRequisition $hiringRequisition): void
    {
        $hiringRequisition->update([
            'status' => HiringRequisitionStatus::Open->value,
            'decided_at' => now(),
        ]);
    }
}