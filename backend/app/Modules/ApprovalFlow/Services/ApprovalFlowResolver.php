<?php

namespace App\Modules\ApprovalFlow\Services;

use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\ApprovalFlow\Models\ApprovalFlowAssignment;
use App\Modules\Employee\Models\Employee;

class ApprovalFlowResolver
{
    /**
     * Resolusi ApprovalFlow yang berlaku untuk seorang Employee, mengikuti
     * cascading order ala Mekari Talenta — dari paling spesifik ke paling umum:
     *
     *   1. Employee   — assignment manual langsung ke orang ini (STEP 30, tidak berubah)
     *   2. Job Level  — flow default untuk semua employee di job level ini
     *   3. Department — flow default untuk semua employee di department ini
     *   4. Branch     — flow default untuk semua employee di branch ini
     *   5. Company    — flow default company-wide (branch/department/job_level semua NULL)
     *
     * Berhenti di tier pertama yang match. Return null kalau tidak ada satupun
     * yang cocok (artinya: tidak butuh approval, caller yang menentukan artinya apa).
     */
    public function resolveFor(Employee $employee): ?ApprovalFlow
    {
        $assignment = ApprovalFlowAssignment::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->first();

        if ($assignment) {
            return $assignment->approvalFlow;
        }

        if ($employee->job_level_id) {
            $flow = $this->activeFlow($employee->company_id, ['job_level_id' => $employee->job_level_id]);
            if ($flow) {
                return $flow;
            }
        }

        if ($employee->department_id) {
            $flow = $this->activeFlow($employee->company_id, ['department_id' => $employee->department_id]);
            if ($flow) {
                return $flow;
            }
        }

        if ($employee->branch_id) {
            $flow = $this->activeFlow($employee->company_id, ['branch_id' => $employee->branch_id]);
            if ($flow) {
                return $flow;
            }
        }

        return $this->activeFlow($employee->company_id, []);
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
