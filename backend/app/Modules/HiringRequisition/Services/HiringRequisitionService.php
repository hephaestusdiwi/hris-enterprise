<?php

namespace App\Modules\HiringRequisition\Services;

use App\Modules\Employee\Models\Employee;
use App\Modules\HiringRequisition\Enums\HiringRequisitionReason;
use App\Modules\HiringRequisition\Enums\HiringRequisitionStatus;
use App\Modules\HiringRequisition\Exceptions\HiringRequisitionValidationException;
use App\Modules\HiringRequisition\Models\HiringRequisition;
use App\Modules\Position\Models\Position;

class HiringRequisitionService
{
    public function __construct(
        private HiringRequisitionApprovalService $approvalService,
    ) {
    }

    /**
     * @param array{branch_id?: ?int, department_id: int, employment_type: string,
     *              headcount_requested: int, reason: string, replacement_for_employee_id?: ?int,
     *              target_start_date?: ?string, justification: string} $data
     */
    public function submit(Employee $requestedBy, Position $position, array $data): HiringRequisition
    {
        $reason = HiringRequisitionReason::from($data['reason']);

        if ($reason === HiringRequisitionReason::Replacement) {
            if (empty($data['replacement_for_employee_id'])) {
                throw new HiringRequisitionValidationException('Replacement requisition wajib mengisi replacement_for_employee_id.');
            }

            $this->assertReplacementTargetMatchesPosition($data['replacement_for_employee_id'], $position);
        }

        if ($data['headcount_requested'] < 1) {
            throw new HiringRequisitionValidationException('Headcount yang diminta minimal 1.');
        }

        $this->assertNoActiveDuplicateRequisition($position, $data['department_id']);

        $hiringRequisition = HiringRequisition::create([
            'company_id' => $position->company_id,
            'branch_id' => $data['branch_id'] ?? null,
            'department_id' => $data['department_id'],
            'position_id' => $position->id,
            'requested_by_employee_id' => $requestedBy->id,
            'replacement_for_employee_id' => $data['replacement_for_employee_id'] ?? null,
            'reason' => $reason->value,
            'employment_type' => $data['employment_type'],
            'headcount_requested' => $data['headcount_requested'],
            'headcount_filled' => 0,
            'target_start_date' => $data['target_start_date'] ?? null,
            'justification' => $data['justification'],
            'status' => HiringRequisitionStatus::Pending->value,
            'requested_at' => now(),
        ]);

        $this->approvalService->initiate($hiringRequisition, $requestedBy);

        return $hiringRequisition->fresh();
    }

    public function cancel(HiringRequisition $hiringRequisition): HiringRequisition
    {
        if ($hiringRequisition->status !== HiringRequisitionStatus::Pending) {
            throw new HiringRequisitionValidationException('Hanya requisition berstatus pending yang bisa dibatalkan.');
        }

        $hiringRequisition->update([
            'status' => HiringRequisitionStatus::Cancelled->value,
            'decided_at' => now(),
        ]);

        $this->approvalService->cancelApprovalIfAny($hiringRequisition);

        return $hiringRequisition->fresh();
    }

    private function assertReplacementTargetMatchesPosition(int $employeeId, Position $position): void
    {
        $matches = Employee::where('id', $employeeId)
            ->where('position_id', $position->id)
            ->exists();

        if (! $matches) {
            throw new HiringRequisitionValidationException('Employee yang digantikan harus memegang Position yang sama dengan requisition ini.');
        }
    }

    private function assertNoActiveDuplicateRequisition(Position $position, int $departmentId): void
    {
        $exists = HiringRequisition::where('position_id', $position->id)
            ->where('department_id', $departmentId)
            ->whereIn('status', [HiringRequisitionStatus::Pending->value, HiringRequisitionStatus::Open->value])
            ->exists();

        if ($exists) {
            throw new HiringRequisitionValidationException('Sudah ada Hiring Requisition aktif untuk Position dan Department ini.');
        }
    }
}