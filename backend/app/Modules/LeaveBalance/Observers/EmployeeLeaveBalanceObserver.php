<?php

namespace App\Modules\LeaveBalance\Observers;

use App\Modules\Employee\Models\Employee;
use App\Modules\LeaveBalance\Services\LeaveBalanceGenerationService;
use Carbon\Carbon;

class EmployeeLeaveBalanceObserver
{
    public function __construct(private LeaveBalanceGenerationService $service)
    {
    }

    public function created(Employee $employee): void
    {
        $this->service->generateForEmployee($employee, Carbon::now());
    }

    public function updated(Employee $employee): void
    {
        $this->service->generateForEmployee($employee, Carbon::now());
    }
}