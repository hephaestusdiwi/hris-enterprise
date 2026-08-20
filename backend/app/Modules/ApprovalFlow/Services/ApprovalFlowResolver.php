<?php

namespace App\Modules\ApprovalFlow\Services;

use App\Modules\ApprovalFlow\DataTransferObjects\ApprovalScope;
use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\ApprovalFlow\Models\ApprovalFlowAssignment;
use App\Modules\Employee\Models\Employee;

class ApprovalFlowResolver
{
    /**
     * Resolusi ApprovalFlow untuk seorang Employee berdasarkan
     * business process / approval type.
     *
     * Cascading tetap sama seperti sebelumnya.
     * Perubahan hanya: resolver sekarang wajib mengetahui
     * jenis approval yang sedang dicari.
     */
    public function resolveFor(
        Employee $employee,
        string $approvalType
    ): ?ApprovalFlow {
        return $this->resolveForScope(
            ApprovalScope::fromEmployee($employee),
            $approvalType
        );
    }

    /**
     * Core resolver untuk subject apa pun.
     *
     * Cascading:
     *
     *   1. Employee assignment
     *   2. Job Level
     *   3. Department
     *   4. Branch
     *   5. Company-wide
     *
     * Semua tier sekarang dibatasi oleh approval_type.
     */
    public function resolveForScope(
        ApprovalScope $scope,
        string $approvalType
    ): ?ApprovalFlow {
        if ($scope->employeeId) {
            $assignment = ApprovalFlowAssignment::query()
                ->where('employee_id', $scope->employeeId)
                ->where('is_active', true)
                ->first();

            if ($assignment) {
                $flow = $assignment->approvalFlow;

                /*
                 * Assignment juga harus cocok dengan business process.
                 */
                if (
                    $flow &&
                    $flow->approval_type === $approvalType &&
                    $flow->is_active
                ) {
                    return $flow;
                }
            }
        }

        if ($scope->jobLevelId) {
            $flow = $this->activeFlow(
                $scope->companyId,
                $approvalType,
                ['job_level_id' => $scope->jobLevelId]
            );

            if ($flow) {
                return $flow;
            }
        }

        if ($scope->departmentId) {
            $flow = $this->activeFlow(
                $scope->companyId,
                $approvalType,
                ['department_id' => $scope->departmentId]
            );

            if ($flow) {
                return $flow;
            }
        }

        if ($scope->branchId) {
            $flow = $this->activeFlow(
                $scope->companyId,
                $approvalType,
                ['branch_id' => $scope->branchId]
            );

            if ($flow) {
                return $flow;
            }
        }

        return $this->activeFlow(
            $scope->companyId,
            $approvalType,
            []
        );
    }

    /**
     * @param array<string, int> $scope
     */
    private function activeFlow(
        int $companyId,
        string $approvalType,
        array $scope
    ): ?ApprovalFlow {
        $query = ApprovalFlow::query()
            ->where('company_id', $companyId)
            ->where('approval_type', $approvalType)
            ->where('is_active', true);

        foreach (
            ['job_level_id', 'department_id', 'branch_id']
            as $column
        ) {
            if (array_key_exists($column, $scope)) {
                $query->where($column, $scope[$column]);
            } else {
                $query->whereNull($column);
            }
        }

        return $query->first();
    }
}