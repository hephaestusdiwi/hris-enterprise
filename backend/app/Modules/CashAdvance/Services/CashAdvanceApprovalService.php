<?php

namespace App\Modules\CashAdvance\Services;

use App\Models\User;
use App\Modules\ApprovalFlow\Services\ApprovalFlowResolver;
use App\Modules\Attendance\Services\ApprovalStepApproverResolver;
use App\Modules\CashAdvance\Enums\CashAdvanceApprovalRequestStatus;
use App\Modules\CashAdvance\Enums\CashAdvanceApprovalStepDecisionStatus;
use App\Modules\CashAdvance\Enums\CashAdvanceRequestStatus;
use App\Modules\CashAdvance\Exceptions\CashAdvanceApprovalException;
use App\Modules\CashAdvance\Models\CashAdvanceApprovalRequest;
use App\Modules\CashAdvance\Models\CashAdvanceApprovalStepDecision;
use App\Modules\CashAdvance\Models\CashAdvanceRequest;
use Illuminate\Support\Facades\DB;

/**
 * Pola SAMA PERSIS dengan LoanApprovalService/ReimbursementApprovalService --
 * orkestrasi generic ApprovalFlowResolver + ApprovalStepApproverResolver
 * existing, TIDAK bikin approval engine baru.
 */
class CashAdvanceApprovalService
{
    public function __construct(
        private ApprovalStepApproverResolver $resolver,
        private ApprovalFlowResolver $approvalFlowResolver,
    ) {
    }

    public function initiate(CashAdvanceRequest $request): void
    {
        $employee = $request->employee;
        $approvalFlow = $this->approvalFlowResolver->resolveFor(
            $employee,
            'cash_advance'
        );

        if (! $approvalFlow) {
            $this->autoApprove($request);

            return;
        }

        $steps = $approvalFlow->steps()->where('is_active', true)->orderBy('sequence')->get();

        if ($steps->isEmpty()) {
            $this->autoApprove($request);

            return;
        }

        $approvalRequest = CashAdvanceApprovalRequest::create([
            'cash_advance_request_id' => $request->id,
            'employee_id' => $employee->id,
            'approval_flow_id' => $approvalFlow->id,
            'status' => CashAdvanceApprovalRequestStatus::Pending->value,
            'current_step_sequence' => $steps->first()->sequence,
            'requested_at' => now(),
        ]);

        foreach ($steps as $step) {
            CashAdvanceApprovalStepDecision::create([
                'cash_advance_approval_request_id' => $approvalRequest->id,
                'approval_step_id' => $step->id,
                'sequence' => $step->sequence,
                'status' => CashAdvanceApprovalStepDecisionStatus::Pending->value,
            ]);
        }
    }

    public function autoApprove(CashAdvanceRequest $request): void
    {
        $this->applyApproval($request);
    }

    public function cancelApprovalIfAny(CashAdvanceRequest $request): void
    {
        $approvalRequest = $request->approvalRequest;

        if ($approvalRequest && $approvalRequest->status === CashAdvanceApprovalRequestStatus::Pending) {
            $approvalRequest->update(['decided_at' => now()]);
        }
    }

    public function decide(
        CashAdvanceApprovalStepDecision $decision,
        User $actor,
        string $action,
        ?string $notes,
    ): CashAdvanceApprovalRequest {
        $approvalRequest = $decision->request;

        if ($approvalRequest->request->status !== CashAdvanceRequestStatus::PendingApproval) {
            throw new CashAdvanceApprovalException('Request ini sudah tidak berstatus pending approval.');
        }

        if ($approvalRequest->status !== CashAdvanceApprovalRequestStatus::Pending) {
            throw new CashAdvanceApprovalException('Approval request ini sudah tidak pending.');
        }

        if ($decision->sequence !== $approvalRequest->current_step_sequence) {
            throw new CashAdvanceApprovalException('Bukan giliran step ini untuk diputuskan.');
        }

        if ($decision->status !== CashAdvanceApprovalStepDecisionStatus::Pending) {
            throw new CashAdvanceApprovalException('Step ini sudah diputuskan sebelumnya.');
        }

        $eligibleUserIds = $this->resolver->resolveApproverUserIds($decision->approvalStep, $approvalRequest->employee);

        if (! in_array($actor->id, $eligibleUserIds, true)) {
            throw new CashAdvanceApprovalException('Anda tidak berwenang memutuskan approval ini.');
        }

        return DB::transaction(function () use ($decision, $actor, $action, $notes, $approvalRequest) {
            if ($action === 'reject') {
                $decision->update([
                    'status' => CashAdvanceApprovalStepDecisionStatus::Rejected->value,
                    'decided_by_user_id' => $actor->id,
                    'notes' => $notes,
                    'decided_at' => now(),
                ]);

                $approvalRequest->update([
                    'status' => CashAdvanceApprovalRequestStatus::Rejected->value,
                    'decided_at' => now(),
                ]);

                $approvalRequest->request->update([
                    'status' => CashAdvanceRequestStatus::Rejected->value,
                    'rejected_at' => now(),
                ]);

                return $approvalRequest->fresh();
            }

            $decision->update([
                'status' => CashAdvanceApprovalStepDecisionStatus::Approved->value,
                'decided_by_user_id' => $actor->id,
                'notes' => $notes,
                'decided_at' => now(),
            ]);

            $nextStep = CashAdvanceApprovalStepDecision::where('cash_advance_approval_request_id', $approvalRequest->id)
                ->where('sequence', '>', $decision->sequence)
                ->orderBy('sequence')
                ->first();

            if (! $nextStep) {
                $approvalRequest->update([
                    'status' => CashAdvanceApprovalRequestStatus::Approved->value,
                    'decided_at' => now(),
                ]);

                $this->applyApproval($approvalRequest->request);
            } else {
                $approvalRequest->update(['current_step_sequence' => $nextStep->sequence]);
            }

            return $approvalRequest->fresh();
        });
    }

    /**
     * @return array<int, CashAdvanceApprovalStepDecision>
     */
    public function pendingDecisionsForUser(User $user): array
    {
        $decisions = CashAdvanceApprovalStepDecision::query()
            ->where('status', CashAdvanceApprovalStepDecisionStatus::Pending->value)
            ->whereHas('request', fn ($query) => $query->where('status', CashAdvanceApprovalRequestStatus::Pending->value))
            ->with(['approvalStep', 'request.employee', 'request.request.items.category'])
            ->get()
            ->filter(fn (CashAdvanceApprovalStepDecision $decision) => $decision->sequence === $decision->request->current_step_sequence)
            ->filter(function (CashAdvanceApprovalStepDecision $decision) use ($user) {
                $eligibleUserIds = $this->resolver->resolveApproverUserIds($decision->approvalStep, $decision->request->employee);

                return in_array($user->id, $eligibleUserIds, true);
            });

        return $decisions->values()->all();
    }

    private function applyApproval(CashAdvanceRequest $request): void
    {
        $request->update([
            'status' => CashAdvanceRequestStatus::Approved->value,
            'approved_at' => now(),
        ]);
    }
}