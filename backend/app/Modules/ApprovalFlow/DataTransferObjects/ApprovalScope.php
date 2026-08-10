<?php

namespace App\Modules\ApprovalFlow\DataTransferObjects;

use App\Modules\Employee\Models\Employee;

/**
 * Representasi scope organisasi buat resolusi ApprovalFlow — dipisah dari
 * Employee supaya subject non-employee (Payroll Run, dan business-process
 * lain di masa depan) bisa resolve tanpa proxy employee yang secara semantik
 * ga nyambung.
 *
 * employeeId cuma diisi kalau memang ada subject person konkret — itu yang
 * jadi penentu apakah tier ApprovalFlowAssignment & approver_type
 * DirectManager applicable atau tidak.
 */
final class ApprovalScope
{
    public function __construct(
        public readonly int $companyId,
        public readonly ?int $branchId = null,
        public readonly ?int $departmentId = null,
        public readonly ?int $jobLevelId = null,
        public readonly ?int $employeeId = null,
    ) {
    }

    public static function fromEmployee(Employee $employee): self
    {
        return new self(
            companyId: $employee->company_id,
            branchId: $employee->branch_id,
            departmentId: $employee->department_id,
            jobLevelId: $employee->job_level_id,
            employeeId: $employee->id,
        );
    }
}