<?php

namespace App\Modules\Employee\Observers;

use App\Modules\Employee\Models\Employee;
use App\Modules\EmploymentStatus\Models\EmploymentStatus;

class EmployeeEmploymentStatusObserver
{
    private const CODE_ACTIVE = 'ACTIVE';

    private const CODE_RESIGNED = 'RESIGNED';

    public function creating(Employee $employee): void
    {
        if ($employee->employment_status_id) {
            return;
        }

        $employee->employment_status_id = $this->resolveStatusId($employee);
    }

    public function updating(Employee $employee): void
    {
        if (
            ! $employee->isDirty('resign_date')
            || $employee->isDirty('employment_status_id')
        ){
            return;
        }

        $statusId = $this->resolveStatusId($employee);

        if ($statusId) {
            $employee->employment_status_id = $statusId;
        }
    }

    private function resolveStatusId(Employee $employee): ?int
    {
        $code = $employee->resign_date
            ? self::CODE_RESIGNED
            : self::CODE_ACTIVE;

        return EmploymentStatus::where('code', $code)->value('id');
    }
}