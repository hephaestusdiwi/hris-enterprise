<?php

namespace App\Modules\EmployeeMovement\Services;

use App\Models\User;
use App\Modules\ApprovalFlow\Services\ApprovalFlowResolver;
use App\Modules\Attendance\Services\ApprovalStepApproverResolver;
use App\Modules\Employee\Models\Employee;
use App\Modules\EmployeeMovement\Enums\EmployeeMovementApprovalRequestStatus;
use App\Modules\EmployeeMovement\Enums\EmployeeMovementApprovalStepDecisionStatus;
use App\Modules\EmployeeMovement\Enums\EmployeeMovementStatus;
use App\Modules\EmployeeMovement\Exceptions\EmployeeMovementException;
use App\Modules\EmployeeMovement\Models\EmployeeMovement;
use App\Modules\EmployeeMovement\Models\EmployeeMovementApprovalRequest;
use App\Modules\EmployeeMovement\Models\EmployeeMovementApprovalStepDecision;

/**
 * Meniru HiringRequisitionApprovalService 1:1. Behavior "tidak ada ApprovalFlow
 * yang cocok" mengikuti preseden HiringRequisition (diblokir, BUKAN auto-approve
 * seperti Leave) — berlaku sama untuk movement yang diajukan employee sendiri
 * maupun oleh admin/HR, tidak ada bypass.
 */
class EmployeeMovementApprovalService
{
    public function __construct(
        private ApprovalStepApproverResolver $resolver,
        private ApprovalFlowResolver $approvalFlowResolver,
        private EmployeeMovementApplier $applier,
    ) {
    }

    public function initiate(EmployeeMovement $movement, Employee $subjectEmployee): void
    {
        $approvalFlow = $this->approvalFlowResolver->resolveFor(
            $subjectEmployee,
            'employee_movement'
        );

        if (! $approvalFlow) {
            throw new EmployeeMovementException(
                'Tidak ada Approval Flow yang berlaku untuk employee ini. Hubungi admin untuk konfigurasi Approval Flow terlebih dahulu.'
            );
        }

        $steps = $approvalFlow->steps()
            ->where('is_active', true)
            ->orderBy('sequence')
            ->get();

        if ($steps->isEmpty()) {
            throw new EmployeeMovementException(
                'Approval Flow yang berlaku tidak memiliki step aktif. Hubungi admin untuk konfigurasi Approval Step.'
            );
        }

        $request = EmployeeMovementApprovalRequest::create([
            'employee_movement_id' => $movement->id,
            'employee_id' => $subjectEmployee->id,
            'approval_flow_id' => $approvalFlow->id,
            'status' => EmployeeMovementApprovalRequestStatus::Pending->value,
            'current_step_sequence' => $steps->first()->sequence,
            'requested_at' => now(),
        ]);

        foreach ($steps as $step) {
            EmployeeMovementApprovalStepDecision::create([
                'employee_movement_approval_request_id' => $request->id,
                'approval_step_id' => $step->id,
                'sequence' => $step->sequence,
                'status' => EmployeeMovementApprovalStepDecisionStatus::Pending->value,
            ]);
        }
    }

    public function cancelApprovalIfAny(EmployeeMovement $movement): void
    {
        $request = $movement->approvalRequest;

        if ($request && $request->status === EmployeeMovementApprovalRequestStatus::Pending) {
            $request->update(['decided_at' => now()]);
        }
    }

    public function decide(
        EmployeeMovementApprovalStepDecision $decision,
        User $actor,
        string $action,
        ?string $notes,
    ): EmployeeMovementApprovalRequest {
        $request = $decision->request;
        $movement = $request->employeeMovement;

        if ($movement->status !== EmployeeMovementStatus::PendingApproval) {
            throw new EmployeeMovementException('Employee movement ini sudah tidak pending (mungkin sudah dibatalkan).');
        }

        if ($request->status !== EmployeeMovementApprovalRequestStatus::Pending) {
            throw new EmployeeMovementException('Request ini sudah tidak pending.');
        }

        if ($decision->sequence !== $request->current_step_sequence) {
            throw new EmployeeMovementException('Bukan giliran step ini untuk diputuskan.');
        }

        if ($decision->status !== EmployeeMovementApprovalStepDecisionStatus::Pending) {
            throw new EmployeeMovementException('Step ini sudah diputuskan sebelumnya.');
        }

        $eligibleUserIds = $this->resolver->resolveApproverUserIds($decision->approvalStep, $request->employee);

        if (! in_array($actor->id, $eligibleUserIds, true)) {
            throw new EmployeeMovementException('Anda tidak berwenang memutuskan approval ini.');
        }

        if ($action === 'reject') {
            $decision->update([
                'status' => EmployeeMovementApprovalStepDecisionStatus::Rejected->value,
                'decided_by_user_id' => $actor->id,
                'notes' => $notes,
                'decided_at' => now(),
            ]);

            $request->update([
                'status' => EmployeeMovementApprovalRequestStatus::Rejected->value,
                'decided_at' => now(),
            ]);

            $movement->update([
                'status' => EmployeeMovementStatus::Rejected->value,
            ]);

            return $request->fresh();
        }

        $decision->update([
            'status' => EmployeeMovementApprovalStepDecisionStatus::Approved->value,
            'decided_by_user_id' => $actor->id,
            'notes' => $notes,
            'decided_at' => now(),
        ]);

        $nextStep = EmployeeMovementApprovalStepDecision::where('employee_movement_approval_request_id', $request->id)
            ->where('sequence', '>', $decision->sequence)
            ->orderBy('sequence')
            ->first();

        if (! $nextStep) {
            $request->update([
                'status' => EmployeeMovementApprovalRequestStatus::Approved->value,
                'decided_at' => now(),
            ]);

            $movement->update(['status' => EmployeeMovementStatus::Approved->value]);

            // Effective-dated: kalau tanggal berlakunya sudah tiba (atau hari ini),
            // langsung terapkan ke Employee. Kalau masih di masa depan, DIAMKAN —
            // scheduler (employee-movements:apply-due) yang akan menerapkannya nanti.
            $this->applier->applyIfDue($movement->fresh());
        } else {
            $request->update(['current_step_sequence' => $nextStep->sequence]);
        }

        return $request->fresh();
    }

    /**
     * @return array<int, EmployeeMovementApprovalStepDecision>
     */
    public function pendingDecisionsForUser(User $user): array
    {
        $decisions = EmployeeMovementApprovalStepDecision::query()
            ->where('status', EmployeeMovementApprovalStepDecisionStatus::Pending->value)
            ->whereHas('request', fn ($query) => $query->where('status', EmployeeMovementApprovalRequestStatus::Pending->value))
            ->with(['approvalStep', 'request.employee', 'request.employeeMovement'])
            ->get()
            ->filter(fn (EmployeeMovementApprovalStepDecision $decision) => $decision->sequence === $decision->request->current_step_sequence)
            ->filter(function (EmployeeMovementApprovalStepDecision $decision) use ($user) {
                $eligibleUserIds = $this->resolver->resolveApproverUserIds($decision->approvalStep, $decision->request->employee);

                return in_array($user->id, $eligibleUserIds, true);
            });

        return $decisions->values()->all();
    }
}
