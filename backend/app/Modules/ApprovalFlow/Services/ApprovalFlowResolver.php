<?php

namespace App\Modules\ApprovalFlow\Services;

use App\Modules\ApprovalFlow\DataTransferObjects\ApprovalScope;
use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\ApprovalFlow\Models\ApprovalFlowAssignment;
use App\Modules\Employee\Models\Employee;

class ApprovalFlowResolver
{
    /**
     * Resolusi ApprovalFlow untuk seorang Employee — TIDAK BERUBAH secara
     * behavior dari versi sebelumnya. Sekarang cuma wrapper tipis di atas
     * resolveForScope(): bangun ApprovalScope dari atribut employee, lalu
     * delegasikan. Semua caller existing (Leave/Attendance/Loan/Hiring
     * Requisition) hasil-nya identik seperti sebelumnya.
     */
    public function resolveFor(Employee $employee): ?ApprovalFlow
    {
        return $this->resolveForScope(ApprovalScope::fromEmployee($employee));
    }

    /**
     * Core resolver, generik untuk subject apa pun — Employee (lewat
     * resolveFor() di atas) maupun business-process non-employee seperti
     * Payroll Run. Cascading order ala Mekari Talenta, dari paling spesifik
     * ke paling umum:
     *
     *   1. Employee   — assignment manual langsung (cuma applicable kalau scope->employeeId terisi)
     *   2. Job Level
     *   3. Department
     *   4. Branch
     *   5. Company    — flow default company-wide (semua kolom scope NULL)
     *
     * Berhenti di tier pertama yang match. Tier yang datanya NULL di scope
     * (mis. Payroll Run yang ga punya job_level/department) otomatis dilewati.
     */
    public function resolveForScope(ApprovalScope $scope): ?ApprovalFlow
    {
        if ($scope->employeeId) {
            $assignment = ApprovalFlowAssignment::where('employee_id', $scope->employeeId)
                ->where('is_active', true)
                ->first();

            if ($assignment) {
                return $assignment->approvalFlow;
            }
        }

        if ($scope->jobLevelId) {
            $flow = $this->activeFlow($scope->companyId, ['job_level_id' => $scope->jobLevelId]);
            if ($flow) {
                return $flow;
            }
        }

        if ($scope->departmentId) {
            $flow = $this->activeFlow($scope->companyId, ['department_id' => $scope->departmentId]);
            if ($flow) {
                return $flow;
            }
        }

        if ($scope->branchId) {
            $flow = $this->activeFlow($scope->companyId, ['branch_id' => $scope->branchId]);
            if ($flow) {
                return $flow;
            }
        }

        return $this->activeFlow($scope->companyId, []);
    }

    /**
     * @param  array<string, int>  $scope  Kolom scope yang harus terisi (yang lain harus NULL)
     */
    private function activeFlow(int $companyId, array $scope): ?ApprovalFlow
    {
        $query = ApprovalFlow::where('company_id', $companyId)->where('is_active', true);

        foreach (['job_level_id', 'department_id', 'branch_id'] as $column) {
            if (array_key_exists($column, $scope)) {
                $query->where($column, $scope[$column]);
            } else {
                $query->whereNull($column);
            }
        }

        return $query->first();
    }
}